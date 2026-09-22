<?php

declare(strict_types=1);

use App\Models\DocumentReference;
use App\Models\Meeting;
use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('a trip can be saved from a confirmed itinerary', function () {
    $user = $this->makeUser();

    $trip = $this->actingAs($user)->postJson('/api/trips/itinerary', [
        'title' => 'Regno offsite',
        'starts_on' => '2026-10-12',
        'ends_on' => '2026-10-14',
        'segments' => [
            ['type' => 'flight', 'details' => 'BA123', 'from_location' => 'London', 'to_location' => 'Lisbon', 'starts_at' => '2026-10-12 09:00:00'],
        ],
    ])->assertCreated()->json('data');

    expect($trip['title'])->toBe('Regno offsite');
    expect($trip['segments'])->toHaveCount(1);

    $this->actingAs($user)->getJson('/api/trips')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

test('documents are searchable and scoped to the workspace', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();
    $launchpad = WorkspaceContext::where('type', 'LAUNCHPAD')->firstOrFail();

    $this->actingAs($user)->postJson('/api/documents', [
        'title' => 'Q3 board pack',
        'provider' => 'drive',
        'deep_link' => 'https://drive.google.com/x',
    ])->assertCreated();

    DocumentReference::create([
        'user_id' => $user->id,
        'workspace_context_id' => $launchpad->id,
        'title' => 'Launchpad pitch',
        'provider' => 'drive',
    ]);

    $this->actingAs($user)->getJson('/api/documents?q=board')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Q3 board pack');
});

test('global search is source-grounded and context-scoped', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    Meeting::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'Offsite planning',
    ]);

    $this->actingAs($user)->getJson('/api/search?q=offsite')
        ->assertOk()
        ->assertJsonPath('data.meetings.0.title', 'Offsite planning');
});
