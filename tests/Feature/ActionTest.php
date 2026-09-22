<?php

declare(strict_types=1);

use App\Models\ActionAudit;
use App\Models\ActionItem;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('an action can be created and appears in the inbox', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->postJson('/api/actions', [
        'title' => 'Send the investor update',
        'due_date' => now()->addDays(2)->toDateString(),
        'priority' => 'high',
    ])->assertCreated();

    $this->actingAs($user)->getJson('/api/actions?view=inbox')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.priority', 'high');
});

test('an overdue action is flagged', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->postJson('/api/actions', [
        'title' => 'Forgotten task',
        'due_date' => now()->subDay()->toDateString(),
    ])->assertCreated();

    $this->actingAs($user)->getJson('/api/actions?view=overdue')
        ->assertOk()
        ->assertJsonPath('data.0.overdue', true);
});

test('completing an action writes an audit trail', function () {
    $user = $this->makeUser();

    $action = $this->actingAs($user)->postJson('/api/actions', ['title' => 'Prepare deck'])
        ->assertCreated()->json('data');

    $this->actingAs($user)->postJson("/api/actions/{$action['id']}/transition", [
        'status' => 'completed',
        'note' => 'Done after the review',
    ])->assertOk()->assertJsonPath('data.status', 'completed');

    expect(ActionAudit::count())->toBe(2); // created + completed
    expect(ActionAudit::where('to_status', 'completed')->first()->note)->toBe('Done after the review');
});

test('a user cannot transition someone else action', function () {
    $owner = $this->makeUser(['email' => 'owner@okyema.test']);
    $intruder = $this->makeUser(['email' => 'intruder@okyema.test']);

    $action = ActionItem::create([
        'user_id' => $owner->id,
        'workspace_context_id' => $owner->workspaceContexts()->firstOrFail()->id,
        'title' => 'Private task',
    ]);

    $this->actingAs($intruder)->postJson("/api/actions/{$action->id}/transition", ['status' => 'completed'])
        ->assertForbidden();
});
