<?php

use App\Models\Contact;
use App\Models\Workspace;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

test('workspace b cannot read workspace a contacts', function () {
    $contactA = Workspace::factory()->create()->execute(fn () => Contact::factory()->create());
    Workspace::factory()->create()->makeCurrent();

    expect(Contact::find($contactA->id))->toBeNull();
});

test('contact emails are unique per workspace regardless of case', function () {
    Workspace::factory()->create()->execute(fn () => Contact::factory()->create(['email' => 'jane@example.com']));
    Workspace::factory()->create()->makeCurrent();
    Contact::factory()->create(['email' => 'jane@example.com']);

    expect(fn () => DB::transaction(fn () => Contact::factory()->create(['email' => 'JANE@example.com'])))
        ->toThrow(UniqueConstraintViolationException::class);
});
