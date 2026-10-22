<?php

namespace App\Automations\Actions;

use App\Actions\Macros\ApplyMacro;
use App\Automations\AutomationException;
use App\Automations\Context;
use App\Data\Automations\Actions\ApplyMacroConfigData;
use App\Models\Desk;
use App\Models\Macro;
use App\Models\User;
use Closure;

/**
 * Applies a shared macro through {@see ApplyMacro}: its field, tag and status changes, then its
 * body as a note or a reply as the macro says (a reply is guarded as described on {@see PostsComments}).
 * Personal macros can't be used. When saved, the macro must be usable on the automation's desk and
 * visible to the editor.
 */
class ApplyMacroAction extends PostsComments
{
    public function key(): string
    {
        return 'apply_macro';
    }

    public function label(): string
    {
        return __('Apply a macro');
    }

    public function configData(): string
    {
        return ApplyMacroConfigData::class;
    }

    public function contextRules(?User $editor, ?Desk $scope): array
    {
        return [
            'macro_id' => [function (string $attribute, mixed $value, Closure $fail) use ($editor, $scope): void {
                $macro = is_string($value)
                    ? Macro::query()->whereNull('user_id')->when($editor, fn ($query) => $query->visibleTo($editor))->find($value)
                    : null;

                if ($macro === null) {
                    $fail(__('This macro does not exist.'));
                } elseif ($scope !== null && $macro->desk_id !== null && $macro->desk_id !== $scope->getKey()) {
                    $fail(__('This macro belongs to another desk than the automation’s.'));
                }
            }],
        ];
    }

    public function execute(Context $context, array $config): string
    {
        $macro = $this->macro($config);

        $result = app(ApplyMacro::class)->handle($this->actor($context), $context->ticket, $macro);

        if (blank($result['body'])) {
            return __('Applied the macro “:name”.', ['name' => $macro->name]);
        }

        $context->refresh();

        return __('Applied the macro “:name”.', ['name' => $macro->name]).' '.$this->post($context, mb_substr($result['body'], 0, 65535), $result['is_internal']);
    }

    public function describe(Context $context, array $config): string
    {
        return __('Would apply the macro “:name”.', ['name' => $this->macro($config)->name]);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    protected function macro(array $config): Macro
    {
        return Macro::query()->whereNull('user_id')->find(ApplyMacroConfigData::from($config)->macroId)
            ?? throw new AutomationException(__('The macro no longer exists or is personal.'));
    }
}
