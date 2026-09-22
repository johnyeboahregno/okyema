<?php

declare(strict_types=1);

use App\Models\ActionItem;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a due-soon rule finds actions inside its horizon', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    ActionItem::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'Due tomorrow',
        'due_date' => now()->addDay()->toDateString(),
    ]);

    $rule = AutomationRule::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'name' => 'Remind me',
        'trigger' => 'action_due_soon',
        'conditions' => ['days' => 1],
    ]);

    $this->actingAs($user)->postJson("/api/automations/{$rule->id}/run")
        ->assertOk()
        ->assertJsonPath('data.status', 'success')
        ->assertJsonPath('data.result.count', 1)
        ->assertJsonPath('data.result.actions.0', 'Due tomorrow');

    expect(AutomationRun::count())->toBe(1);
});

test('an inactive or unknown trigger still records a run', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    $rule = AutomationRule::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'name' => 'Mystery',
        'trigger' => 'unknown_trigger',
    ]);

    $this->actingAs($user)->postJson("/api/automations/{$rule->id}/run")
        ->assertOk()
        ->assertJsonPath('data.status', 'success');
});

test('rules are scoped to the workspace context', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    AutomationRule::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'name' => 'Regno rule',
        'trigger' => 'action_due_soon',
    ]);

    $this->actingAs($user)->getJson('/api/automations')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
