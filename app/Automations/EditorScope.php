<?php

namespace App\Automations;

use App\Models\Desk;
use App\Models\User;
use LogicException;

/**
 * Who is saving an automation and which desk it is scoped to, for the validation of its actions.
 * Set by the Actions that save automations before they validate the input.
 */
class EditorScope
{
    protected bool $configured = false;

    protected ?User $editor = null;

    public ?string $deskId = null;

    public function set(User $editor, mixed $deskId): void
    {
        $this->configured = true;
        $this->editor = $editor;
        $this->deskId = is_string($deskId) ? $deskId : null;
    }

    /**
     * @throws LogicException When nobody has set who is saving, so a caller can't skip the editor checks by forgetting.
     */
    public function editor(): User
    {
        return $this->editor ?? throw new LogicException('Set the editor on EditorScope before validating an automation definition.');
    }

    public function desk(): ?Desk
    {
        return $this->deskId === null ? null : Desk::query()->find($this->deskId);
    }
}
