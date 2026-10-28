<?php

namespace App\Actions\Install;

use App\Data\Install\ServiceCheckData;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Laravel\Horizon\Contracts\MasterSupervisorRepository;
use RuntimeException;
use Throwable;

class CheckServices
{
    /**
     * Whether each service a self-hosted install needs is reachable. Informational only: a
     * failing check never blocks the install.
     *
     * @return list<ServiceCheckData>
     */
    public function handle(): array
    {
        return [
            $this->check('Postgres', fn (): string => (string) DB::selectOne('select version() as version')->version),
            $this->check('Redis', function (): string {
                Redis::connection()->ping();

                return __('Connected');
            }),
            $this->check('Horizon', function (): string {
                if (app(MasterSupervisorRepository::class)->all() === []) {
                    throw new RuntimeException(__('Not running. Start it with `php artisan horizon`.'));
                }

                return __('Running');
            }),
            $this->check('Reverb', function (): string {
                $host = (string) config('reverb.servers.reverb.host');
                $port = (int) config('reverb.servers.reverb.port');
                $socket = @fsockopen($host === '0.0.0.0' ? '127.0.0.1' : $host, $port, $code, $message, 1);

                if ($socket === false) {
                    throw new RuntimeException(__('Nothing is listening on :host::port. Start it with `php artisan reverb:start`.', ['host' => $host, 'port' => $port]));
                }

                fclose($socket);

                return __('Listening on :host::port', ['host' => $host, 'port' => $port]);
            }),
        ];
    }

    /**
     * @param  Closure(): string  $probe  returns the detail, or throws why the service is down
     */
    private function check(string $name, Closure $probe): ServiceCheckData
    {
        try {
            return new ServiceCheckData($name, true, $probe());
        } catch (Throwable $exception) {
            return new ServiceCheckData($name, false, $exception->getMessage());
        }
    }
}
