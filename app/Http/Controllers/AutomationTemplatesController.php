<?php

namespace App\Http\Controllers;

use App\Automations\Templates;
use App\Data\Automations\AutomationTemplateData;
use App\Models\Automation;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AutomationTemplatesController extends Controller
{
    /**
     * The gallery of ready-made automations, where a new automation starts.
     */
    public function __invoke(): Response
    {
        Gate::authorize('viewAny', Automation::class);

        return Inertia::render('automations/Templates', [
            'templates' => AutomationTemplateData::collect(Templates::all()),
        ]);
    }
}
