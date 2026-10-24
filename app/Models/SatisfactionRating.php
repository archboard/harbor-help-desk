<?php

namespace App\Models;

use App\Enums\SatisfactionScore;
use App\Models\Concerns\BelongsToWorkspace;
use Carbon\CarbonImmutable;
use Database\Factories\SatisfactionRatingFactory;
use GrantHolle\Scru128Laravel\Concerns\HasScru128Ids;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A contact's verdict on how their resolved ticket was handled: one per ticket.
 *
 * @property string $id
 * @property string $workspace_id
 * @property string $ticket_id
 * @property string $contact_id
 * @property SatisfactionScore $rating
 * @property string|null $comment
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Contact $contact
 * @property-read Ticket|null $ticket
 * @property-read Workspace $workspace
 *
 * @method static \Database\Factories\SatisfactionRatingFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating whereComment($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating whereContactId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating whereRating($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating whereTicketId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|SatisfactionRating whereWorkspaceId($value)
 *
 * @mixin \Eloquent
 */
#[Unguarded]
class SatisfactionRating extends Model
{
    /*
    |--------------------------------------------------------------------------
    | Properties
    |--------------------------------------------------------------------------
    */

    /** @use HasFactory<SatisfactionRatingFactory> */
    use BelongsToWorkspace, HasFactory, HasScru128Ids;

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
            'rating' => SatisfactionScore::class,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /**
     * @return BelongsTo<Ticket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * @return BelongsTo<Contact, $this>
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Methods
    |--------------------------------------------------------------------------
    */

    public function idPrefix(): string
    {
        return 'csr_';
    }
}
