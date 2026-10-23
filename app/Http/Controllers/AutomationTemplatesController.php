<?php

namespace App\Http\Controllers;

use App\Automations\Templates;
use App\Data\Automations\AutomationTemplateData;
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
