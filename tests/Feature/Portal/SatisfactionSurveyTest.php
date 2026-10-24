<?php

use App\Actions\Tickets\RateTicket;
use App\Enums\SatisfactionScore;
use App\Enums\TicketEventType;
use App\Enums\TicketStatus;
use App\Models\Contact;
use App\Models\SatisfactionRating;
use App\Models\Ticket;
use App\Models\Workspace;
use App\Notifications\SatisfactionSurveyNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\InWorkspace;

uses(InWorkspace::class);

beforeEach(function () {
    $this->workspace->update(['settings' => ['surveys_enabled' => true]]);
    $this->contact = Contact::factory()->create();
    $this->ticket = Ticket::factory()->create(['requester_id' => $this->contact->id]);
});

/**
 * Solve the ticket the way an Action does: change the status, then record the event.
 */
function solve(Ticket $ticket): void
{
    $from = $ticket->status;
    $ticket->update(['status' => TicketStatus::SOLVED]);
    $ticket->recordEvent(TicketEventType::STATUS_CHANGED, null, ['from' => $from->value, 'to' => TicketStatus::SOLVED->value]);
}

function surveyLink(SatisfactionScore $score, Ticket $ticket): string
{
    return Workspace::current()->url(URL::temporarySignedRoute(
        'portal.ratings.show',
        now()->addDays(30),
        ['ticket' => $ticket->id, 'rating' => $score->value],
        absolute: false,
    ));
}

test('the survey is sent once when the ticket is first solved', function () {
    Notification::fake();

    solve($this->ticket);

    Notification::assertSentTo($this->contact, SatisfactionSurveyNotification::class, function (SatisfactionSurveyNotification $notification): bool {
        $html = (string) $notification->toMail($this->contact)->render();

        return str_contains($html, 'href="'.e($notification->goodUrl).'"')
            && str_contains($html, 'href="'.e($notification->badUrl).'"')
            && str_starts_with($notification->goodUrl, $this->workspace->url('portal/tickets/'.$this->ticket->id.'/rate/good'))
            && str_contains($notification->badUrl, '/rate/bad?')
            && str_contains($notification->badUrl, 'signature=');
    });
    expect($this->ticket->fresh()->survey_sent_at)->not->toBeNull();

    $this->ticket->update(['status' => TicketStatus::OPEN]);
    solve($this->ticket->fresh());

    Notification::assertSentToTimes($this->contact, SatisfactionSurveyNotification::class, 1);
});

test('no survey is sent while surveys are off', function () {
    $this->workspace->update(['settings' => ['surveys_enabled' => false]]);
    Notification::fake();

    solve($this->ticket);

    Notification::assertNothingSent();
});

test('no survey is sent for a ticket that is already rated', function () {
    SatisfactionRating::factory()->create(['ticket_id' => $this->ticket->id]);
    Notification::fake();

    solve($this->ticket);

    Notification::assertNothingSent();
});

test('opening the link shows the page and records nothing', function () {
    $this->ticket->update(['status' => TicketStatus::SOLVED]);

    $this->get(surveyLink(SatisfactionScore::BAD, $this->ticket))
        ->assertInertia(fn (Assert $page) => $page->component('portal/Rate')->where('rating', 'bad'));

    expect(SatisfactionRating::count())->toBe(0);
});

test('submitting rates the ticket as its requester, and rating again updates it', function () {
    $this->ticket->update(['status' => TicketStatus::SOLVED]);
    $url = surveyLink(SatisfactionScore::GOOD, $this->ticket);

    $this->post($url, ['rating' => 'good', 'comment' => 'Quick!'])->assertRedirect();
    $this->post($url, ['rating' => 'bad'])->assertRedirect();

    $rating = SatisfactionRating::sole();
    expect($rating->rating)->toBe(SatisfactionScore::BAD)
        ->and($rating->comment)->toBeNull()
        ->and($rating->contact_id)->toBe($this->contact->id)
        ->and($this->ticket->events()->where('type', TicketEventType::SATISFACTION_RATED)->count())->toBe(2)
        ->and($this->ticket->events()->where('type', TicketEventType::SATISFACTION_RATED)->reorder()->latest('id')->first()->properties)->toBe(['rating' => 'bad']);
});

test('a tampered or expired link is rejected', function () {
    $this->ticket->update(['status' => TicketStatus::SOLVED]);
    $url = surveyLink(SatisfactionScore::GOOD, $this->ticket);

    $this->get(str_replace('/good', '/bad', $url))->assertForbidden();
    $this->post(str_replace('/good', '/bad', $url), ['rating' => 'bad'])->assertForbidden();
    $this->post($this->workspace->url('portal/tickets/'.$this->ticket->id.'/rate/good'), ['rating' => 'good'])->assertForbidden();

    $this->travel(31)->days();
    $this->get($url)->assertForbidden();
    $this->post($url, ['rating' => 'good'])->assertForbidden();
    expect(SatisfactionRating::count())->toBe(0);
});

test('the link is a 404 while surveys are off', function () {
    $this->workspace->update(['settings' => ['surveys_enabled' => false]]);

    $this->get(surveyLink(SatisfactionScore::GOOD, $this->ticket))->assertNotFound();
});

test('the link works with the portal switched off', function () {
    $this->workspace->update(['settings' => ['surveys_enabled' => true, 'portal_enabled' => false]]);

    $this->get(surveyLink(SatisfactionScore::GOOD, $this->ticket))->assertOk();
});

test('an unresolved ticket cannot be rated', function () {
    $this->post(surveyLink(SatisfactionScore::GOOD, $this->ticket), ['rating' => 'good'])->assertSessionHasErrors('rating');

    expect(fn () => app(RateTicket::class)->handle($this->contact, $this->ticket, ['rating' => 'good']))
        ->toThrow(ValidationException::class);
    expect(SatisfactionRating::count())->toBe(0);
});

test('only the requester can rate', function () {
    $this->ticket->update(['status' => TicketStatus::SOLVED]);

    expect(fn () => app(RateTicket::class)->handle(Contact::factory()->create(), $this->ticket, ['rating' => 'good']))
        ->toThrow(ValidationException::class);
});

test('a link signed for workspace A\'s ticket is a 404 on workspace B', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    URL::forceRootUrl(null);
    $other = Workspace::factory()->create(['subdomain' => 'other', 'settings' => ['surveys_enabled' => true]]);
    $this->workspace->update(['subdomain' => 'acme']);
    $this->ticket->update(['status' => TicketStatus::SOLVED]);

    $path = URL::temporarySignedRoute('portal.ratings.show', now()->addDay(), ['ticket' => $this->ticket->id, 'rating' => 'good'], absolute: false);

    $this->get('http://acme.harbor.test'.$path)->assertOk();
    $this->get('http://other.harbor.test'.$path)->assertNotFound();
    $this->post('http://other.harbor.test'.$path, ['rating' => 'good'])->assertNotFound();
    expect(SatisfactionRating::count())->toBe(0)
        ->and($other->execute(fn () => SatisfactionRating::count()))->toBe(0);
});

test('a contact rates and changes the rating from the portal ticket', function () {
    solve($this->ticket);
    $this->actingAs($this->contact, 'contact');

    $this->get(route('portal.tickets.show', $this->ticket))
        ->assertInertia(fn (Assert $page) => $page->where('canRate', true)->where('ticket.satisfaction_rating', null));

    $this->post(route('portal.tickets.rating.store', $this->ticket), ['rating' => 'bad', 'comment' => 'Slow'])->assertRedirect();
    $this->post(route('portal.tickets.rating.store', $this->ticket), ['rating' => 'good'])->assertRedirect();

    expect(SatisfactionRating::sole())->rating->toBe(SatisfactionScore::GOOD)->comment->toBeNull();
    $this->get(route('portal.tickets.show', $this->ticket))
        ->assertInertia(fn (Assert $page) => $page->where('ticket.satisfaction_rating.rating', 'good'));
});

test('the portal rating needs a solved ticket of your own', function () {
    $this->actingAs($this->contact, 'contact');

    $this->post(route('portal.tickets.rating.store', $this->ticket), ['rating' => 'good'])->assertSessionHasErrors('rating');

    solve($this->ticket);
    $this->post(route('portal.tickets.rating.store', Ticket::factory()->create()), ['rating' => 'good'])->assertNotFound();

    expect(SatisfactionRating::count())->toBe(0);
});

test('the portal rating is a 404 while surveys are off', function () {
    solve($this->ticket);
    $this->workspace->update(['settings' => ['surveys_enabled' => false]]);
    $this->actingAs($this->contact, 'contact');
    Workspace::forgetCurrent();
    $this->post(route('portal.tickets.rating.store', $this->ticket), ['rating' => 'good'])->assertNotFound();
    $this->get(route('portal.tickets.show', $this->ticket))->assertInertia(fn (Assert $page) => $page->where('canRate', false));
    expect(SatisfactionRating::count())->toBe(0);
});

test('a contact cannot rate another workspace\'s ticket from the portal', function () {
    config(['harbor.multitenant' => true, 'harbor.domain' => 'harbor.test']);
    URL::forceRootUrl(null);
    $this->workspace->update(['subdomain' => 'acme']);
    $other = Workspace::factory()->create(['subdomain' => 'other', 'settings' => ['surveys_enabled' => true]]);
    $foreign = $other->execute(function () {
        $ticket = Ticket::factory()->create(['status' => TicketStatus::SOLVED]);

        return $ticket;
    });
    $this->workspace->makeCurrent();
    $this->actingAs($this->contact, 'contact');

    $this->post("http://acme.harbor.test/portal/tickets/{$foreign->id}/rating", ['rating' => 'good'])->assertNotFound();
    expect($other->execute(fn () => SatisfactionRating::count()))->toBe(0);
});
