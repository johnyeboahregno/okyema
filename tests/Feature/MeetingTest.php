<?php

declare(strict_types=1);

use App\Models\ActionItem;
use App\Models\Meeting;
use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a meeting can be created, noted, summarised and have a decision converted', function () {
    $user = $this->makeUser();

    $meeting = $this->actingAs($user)->postJson('/api/meetings', [
        'title' => 'Product kickoff',
        'agenda' => 'Decide launch plan',
        'starts_at' => '2026-09-30T09:00:00Z',
    ])->assertCreated()->json('data');

    $this->actingAs($user)->postJson("/api/meetings/{$meeting['id']}/participants", [
        'name' => 'Ama Mensah',
        'organisation' => 'Regno',
    ])->assertCreated();

    $this->actingAs($user)->postJson("/api/meetings/{$meeting['id']}/notes", [
        'body' => "Decision: Launch in October\nAction: Finalise pricing @John by 2026-10-01 #high",
    ])->assertCreated();

    $generated = $this->actingAs($user)->postJson("/api/meetings/{$meeting['id']}/generate")
        ->assertOk()
        ->json('data');

    expect($generated['summary']['body'])->toContain('decision');
    expect($generated['decisions'])->toHaveCount(1);
    expect($generated['decisions'][0]['title'])->toBe('Launch in October');
    expect($generated['decisions'][0]['citations'])->toHaveCount(1);
    expect($generated['proposed_actions'])->toHaveCount(1);
    expect($generated['proposed_actions'][0]['title'])->toBe('Finalise pricing');

    // Explicitly convert the decision into an action.
    $action = $this->actingAs($user)->postJson("/api/decisions/{$generated['decisions'][0]['id']}/convert")
        ->assertCreated()
        ->json('data');

    expect($action['title'])->toBe('Launch in October');
    expect($action['citations'])->toHaveCount(1);
    expect(ActionItem::count())->toBe(1);
});

test('generating a summary with no notes is rejected', function () {
    $user = $this->makeUser();
    $meeting = Meeting::create([
        'user_id' => $user->id,
        'workspace_context_id' => WorkspaceContext::where('type', 'REGNO')->firstOrFail()->id,
        'title' => 'Empty meeting',
    ]);

    $this->actingAs($user)->postJson("/api/meetings/{$meeting->id}/generate")
        ->assertUnprocessable();
});

test('a meeting is only visible in its own workspace context', function () {
    $user = $this->makeUser();
    $launchpad = WorkspaceContext::where('type', 'LAUNCHPAD')->firstOrFail();

    $this->actingAs($user)->postJson('/api/meetings', ['title' => 'Regno only'])->assertCreated();

    // Switch to Launchpad — the Regno meeting must disappear.
    $this->actingAs($user)->postJson("/api/contexts/{$launchpad->id}/activate")->assertOk();

    $this->actingAs($user)->getJson('/api/meetings')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
