<?php

namespace App\Providers;

use Spatie\LaravelTypeScriptTransformer\LaravelData\LaravelDataTypeScriptTransformerExtension;
use Spatie\LaravelTypeScriptTransformer\TypeScriptTransformerApplicationServiceProvider;
use Spatie\TypeScriptTransformer\Formatters\Formatter;
use Spatie\TypeScriptTransformer\Transformers\EnumTransformer;
use Spatie\TypeScriptTransformer\TypeScriptTransformerConfigFactory;
use Spatie\TypeScriptTransformer\Writers\GlobalNamespaceWriter;
use Symfony\Component\Process\Process;

/**
 * `php artisan typescript:transform` writes the Data classes and enums to
 * `resources/js/types/generated.d.ts` as global `App.Data.*` and `App.Enums.*` types, formatted
 * with the project's formatter (`vp fmt`).
 */
class TypeScriptTransformerServiceProvider extends TypeScriptTransformerApplicationServiceProvider
{
    protected function configure(TypeScriptTransformerConfigFactory $config): void
    {
        $config
            ->extension(new LaravelDataTypeScriptTransformerExtension)
            ->transformer(EnumTransformer::class)
            ->transformDirectories(app_path('Data'), app_path('Enums'))
            ->outputDirectory(resource_path('js/types'))
            ->writer(new GlobalNamespaceWriter('generated.d.ts'))
            ->formatter(new class implements Formatter
            {
                /** @param  array<int, string>  $files */
                public function format(array $files): void
                {
                    (new Process(['npx', 'vp', 'fmt', ...$files], base_path()))->mustRun();
                }
            })
            ->withoutManifest();
    }
}
