<?php

namespace App\Mcp\Tools;

use App\Data\Desks\DeskData;
use App\Data\Macros\MacroData;
use App\Data\SlaPolicies\SlaPolicyData;
use App\Data\Tags\TagData;
use App\Data\Users\UserData;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\SlaPolicy;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description('Describe the workspace as the current agent sees it: their desks (with custom fields and members), tags, macros, SLA policies, the ticket statuses and priorities, and who they are. Call this first.')]
#[IsReadOnly]
class DescribeWorkspaceTool extends Tool
{
    public function handle(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        Gate::forUser($user)->authorize('viewAny', Desk::class);
        Gate::forUser($user)->authorize('viewAny', Macro::class);
        Gate::forUser($user)->authorize('viewAny', SlaPolicy::class);

        return Response::json([
            'user' => UserData::from($user),
            'desks' => DeskData::collect(Desk::visibleTo($user)->with(['customFields', 'users'])->orderBy('name')->get()),
            'tags' => TagData::collect(Tag::query()->orderBy('name')->get()),
            'macros' => MacroData::collect(Macro::visibleTo($user)->orderBy('name')->get()),
            'sla_policies' => SlaPolicyData::collect(SlaPolicy::query()->with('businessSchedule')->orderBy('name')->orderBy('id')->get()),
            'statuses' => array_column(TicketStatus::cases(), 'value'),
            'priorities' => array_column(TicketPriority::cases(), 'value'),
        ]);
    }
}
