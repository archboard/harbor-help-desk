<?php

namespace App\Http\Controllers\Api\V1;

use App\Automations\Templates;
use App\Data\Automations\AutomationTemplateData;
use App\Http\Controllers\Controller;
use App\Models\Automation;
use Illuminate\Support\Facades\Gate;

class AutomationTemplatesController extends Controller
{
    /**
     * The ready-made automations the builder can start from.
     *
     * @return list<AutomationTemplateData>
     */
    public function __invoke(): array
    {
        Gate::authorize('viewAny', Automation::class);

        return Templates::all();
    }
}
