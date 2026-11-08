<?php

namespace App\Mcp\Tools;

use App\Actions\Reports\BuildReport;
use App\Data\Reports\ReportFiltersData;
use App\Enums\Report;
use App\Models\User;
use App\Support\DataSchema;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Ticket statistics: one report over a date range and the desks the agent can see.')]
#[IsReadOnly]
class GetTicketStatsTool extends Tool
{
    public function handle(Request $request, BuildReport $buildReport): Response
    {
        /** @var User $user */
        $user = $request->user();

        $report = Report::tryFrom((string) $request->get('report'));

        if ($report === null) {
            return Response::error('Unknown report. Use one of: '.implode(', ', array_column(Report::cases(), 'value')).'.');
        }

        return Response::json($buildReport->handle($user, $report, $request->except('report')));
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'report' => $schema->string()->enum(Report::class)->required(),
            ...DataSchema::for(ReportFiltersData::class),
        ];
    }
}
