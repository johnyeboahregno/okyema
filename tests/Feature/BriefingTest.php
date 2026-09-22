<?php

declare(strict_types=1);

use App\Models\ActionItem;
use App\Models\Decision;
use App\Models\Meeting;
use App\Models\Trip;
use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the morning briefing reports facts and a summary', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    ActionItem::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'Overdue report',
        'due_date' => now()->subDay()->toDateString(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/briefing/morning')->assertOk()->json('data');

    expect($response['context'])->toBe('REGNO');
    expect($response['overdue_actions'])->toContain('Overdue report');
    expect($response['summary'])->toContain('1 overdue action');
});

test('the weekly briefing covers the next seven days', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->getJson('/api/briefing/weekly')
        ->assertOk()
        ->assertJsonStructure(['data' => ['week_start', 'week_end', 'meetings', 'actions_due', 'summary']]);
});

test('the dashboard includes a factual briefing, recent decisions and travel alerts', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    $meeting = Meeting::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'Kickoff',
    ]);

    Decision::create([
        'meeting_id' => $meeting->id,
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'Launch in October',
    ]);

    Trip::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'Offsite',
        'starts_on' => now()->addDays(3)->toDateString(),
    ]);

    $response = $this->actingAs($user)->getJson('/api/dashboard')->json('data');

    expect($response['briefing'])->toContain('meeting');
    expect($response['recent_decisions'][0]['title'])->toBe('Launch in October');
    expect($response['travel_alerts'])->toBe(1);
});
