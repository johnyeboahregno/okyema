<?php

declare(strict_types=1);

use App\Models\Calendar;
use App\Models\Event;
use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function calendarFor(WorkspaceContext $context, string $provider = 'google'): Calendar
{
    return Calendar::create([
        'user_id' => $context->memberships()->first()->user_id,
        'workspace_context_id' => $context->id,
        'name' => $provider.' Calendar',
        'provider' => $provider,
        'timezone' => 'UTC',
    ]);
}

test('the agenda returns events for the active context only', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();
    $launchpad = WorkspaceContext::where('type', 'LAUNCHPAD')->firstOrFail();

    Event::create([
        'calendar_id' => calendarFor($regno)->id,
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'Regno stand-up',
        'starts_at' => '2026-09-23T09:00:00Z',
        'ends_at' => '2026-09-23T09:45:00Z',
        'provider' => 'google',
        'provider_event_id' => 'regno-1',
    ]);

    Event::create([
        'calendar_id' => calendarFor($launchpad, 'microsoft')->id,
        'user_id' => $user->id,
        'workspace_context_id' => $launchpad->id,
        'title' => 'Launchpad sync',
        'starts_at' => '2026-09-23T10:00:00Z',
        'ends_at' => '2026-09-23T10:30:00Z',
        'provider' => 'microsoft',
        'provider_event_id' => 'launchpad-1',
    ]);

    $response = $this->actingAs($user)->getJson('/api/agenda?date=2026-09-23');

    $response->assertOk();
    expect($response->json('data.events'))->toHaveCount(1);
    expect($response->json('data.events.0.title'))->toBe('Regno stand-up');
});

test('the agenda excludes cancelled events and flags conflicts', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();
    $calendar = calendarFor($regno);

    Event::create([
        'calendar_id' => $calendar->id,
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'A',
        'starts_at' => '2026-09-23T09:00:00Z',
        'ends_at' => '2026-09-23T10:00:00Z',
        'provider' => 'google',
        'provider_event_id' => 'a',
    ]);
    Event::create([
        'calendar_id' => $calendar->id,
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'B',
        'starts_at' => '2026-09-23T09:30:00Z',
        'ends_at' => '2026-09-23T10:30:00Z',
        'provider' => 'google',
        'provider_event_id' => 'b',
    ]);
    Event::create([
        'calendar_id' => $calendar->id,
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'Cancelled',
        'starts_at' => '2026-09-23T11:00:00Z',
        'ends_at' => '2026-09-23T12:00:00Z',
        'state' => 'cancelled',
        'provider' => 'google',
        'provider_event_id' => 'c',
    ]);

    $events = $this->actingAs($user)
        ->getJson('/api/agenda?date=2026-09-23')
        ->json('data.events');

    expect($events)->toHaveCount(2);
    expect(collect($events)->where('has_conflict', true)->pluck('title')->all())->toBe(['B']);
});

test('the timeline returns events across a range', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();
    $calendar = calendarFor($regno);

    Event::create([
        'calendar_id' => $calendar->id,
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'title' => 'Friday review',
        'starts_at' => '2026-09-25T09:00:00Z',
        'ends_at' => '2026-09-25T10:00:00Z',
        'provider' => 'google',
        'provider_event_id' => 'friday',
    ]);

    $response = $this->actingAs($user)->getJson('/api/timeline?from=2026-09-23&to=2026-09-30');

    $response->assertOk();
    expect($response->json('data.events'))->toHaveCount(1);
    expect($response->json('data.events.0.title'))->toBe('Friday review');
});

test('guests cannot read the agenda', function () {
    $this->getJson('/api/agenda')->assertUnauthorized();
});
