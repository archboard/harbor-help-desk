<?php

namespace App\Automations\Actions;

use App\Actions\Tickets\UpdateTicket;
use App\Automations\AutomationException;
use App\Automations\Context;
use App\Data\Automations\Actions\SetSlaPolicyConfigData;
use App\Models\SlaPolicy;

/**
 * Sets the ticket's own SLA policy, or clears it so the desk's applies, through {@see UpdateTicket}.
 */
class SetSlaPolicy extends ConfiguredAction
{
    public function key(): string
    {
        return 'set_sla_policy';
    }

    public function label(): string
    {
        return __('Set the SLA policy');
    }

    public function configData(): string
    {
        return SetSlaPolicyConfigData::class;
    }

    public function execute(Context $context, array $config): string
    {
        $policy = $this->policy($config);

        app(UpdateTicket::class)->handle($this->actor($context), $context->ticket, ['sla_policy_id' => $policy?->getKey()]);

        return $policy === null ? __('Cleared the SLA policy override.') : __('SLA policy set to :name.', ['name' => $policy->name]);
    }

    public function describe(Context $context, array $config): string
    {
        $policy = $this->policy($config);

        return $policy === null ? __('Would clear the SLA policy override.') : __('Would set the SLA policy to :name.', ['name' => $policy->name]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function policy(array $config): ?SlaPolicy
    {
        $id = SetSlaPolicyConfigData::from($config)->slaPolicyId;

        return $id === null ? null : SlaPolicy::query()->find($id) ?? throw new AutomationException(__('The SLA policy no longer exists.'));
    }
}
