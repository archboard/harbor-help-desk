<?php

namespace App\Models;

use App\Enums\CustomFieldType;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\CustomFieldFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A workspace-defined field that tickets record a value for, e.g. an order number. Desks
 * opt in to the fields they use. Values live on the ticket, keyed by this field's id.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $name
 * @property CustomFieldType $type
 * @property list<string>|null $options
 * @property bool $is_required
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Collection<int, Desk> $desks
 * @property-read int|null $desks_count
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\CustomFieldFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereIsRequired($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereOptions($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField wherePosition($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|CustomField whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class CustomField extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<CustomFieldFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_required' => false,
        'position' => 0,
    ];

    /*
    |--------------------------------------------------------------------------
    | Casts
    |--------------------------------------------------------------------------
    */

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => CustomFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * The desks that have this field enabled.
     *
     * @return BelongsToMany<Desk, $this>
     */
    public function desks(): BelongsToMany
    {
        return $this->belongsToMany(Desk::class, 'custom_field_desk');
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'cfd_';
    }
}
