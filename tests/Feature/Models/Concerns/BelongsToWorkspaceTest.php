<?php

use App\Models\Workspace;
use Tests\Fixtures\WorkspaceNote;

beforeEach(function () {
    WorkspaceNote::createTable();

    $this->workspaceA = Workspace::factory()->create();
    $this->workspaceB = Workspace::factory()->create();
});

test('creating fills workspace id from the current workspace', function () {
    $this->workspaceA->makeCurrent();

    $note = WorkspaceNote::create(['body' => 'Hello']);

    expect($note->workspace_id)->toBe($this->workspaceA->id);
});

test('workspace b cannot read workspace a records', function () {
    $noteA = $this->workspaceA->execute(fn () => WorkspaceNote::create(['body' => 'A']));
    $noteB = $this->workspaceB->execute(fn () => WorkspaceNote::create(['body' => 'B']));

    $this->workspaceB->makeCurrent();

    expect(WorkspaceNote::pluck('id')->all())->toBe([$noteB->id]);
    expect(WorkspaceNote::find($noteA->id))->toBeNull();
});

test('workspace b cannot change workspace a records', function () {
    $noteA = $this->workspaceA->execute(fn () => WorkspaceNote::create(['body' => 'A']));

    $this->workspaceB->makeCurrent();

    expect(WorkspaceNote::whereKey($noteA->id)->update(['body' => 'changed']))->toBe(0);
    expect(WorkspaceNote::whereKey($noteA->id)->delete())->toBe(0);
    $this->assertDatabaseHas('workspace_notes', ['id' => $noteA->id, 'body' => 'A']);
});

test('queries match nothing without a current workspace', function () {
    $this->workspaceA->execute(fn () => WorkspaceNote::create(['body' => 'A']));

    expect(Workspace::current())->toBeNull();
    expect(WorkspaceNote::count())->toBe(0);
});
