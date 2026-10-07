<?php

declare(strict_types=1);

use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Models\ApprovalRequest;
use App\Models\ConnectorAccount;
use App\Models\WorkspaceContext;
use App\Services\AI\AIProviderInterface;
use App\Services\Assistant\AssistantService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

test('the assistant endpoint requires authentication', function () {
    $this->postJson('/api/assistant', ['request' => 'hello'])
        ->assertUnauthorized();
});

test('a typed request returns a result when AI is disabled', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->postJson('/api/assistant', ['request' => 'What do I need to do today?'])
        ->assertOk()
        ->assertJsonStructure(['data' => ['answer', 'sources', 'approval', 'notice']])
        ->assertJsonPath('data.approval', null);
});

test('the assistant honours context isolation', function () {
    $user = $this->makeUser();
    $other = $this->makeUser(['email' => 'other@example.com']);
    $otherContext = WorkspaceContext::where('user_id', $other->id)->firstOrFail();

    $this->actingAs($user)->postJson('/api/assistant', [
        'request' => 'hello',
        'workspace_context_id' => $otherContext->id,
    ])->assertForbidden();
});

test('a Notion-backed answer includes working source links', function () {
    config()->set('okyema.notion.databases.REGNO', 'db-regno');

    Http::fake(function ($request) {
        if (str_contains($request->url(), '/v1/search')) {
            return Http::response([
                'results' => [
                    [
                        'parent' => ['database_id' => 'db-regno'],
                        'url' => 'https://www.notion.so/acme/q3-plan',
                        'properties' => [
                            'Name' => ['type' => 'title', 'title' => [['plain_text' => 'Q3 plan']]],
                        ],
                    ],
                ],
            ]);
        }

        return Http::response([]);
    });

    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => ConnectorProvider::Notion,
        'status' => ConnectorStatus::Connected,
        'access_token' => 'notion-token',
    ]);

    $this->actingAs($user)->postJson('/api/assistant', [
        'request' => 'Q3 plan',
        'workspace_context_id' => $regno->id,
    ])->assertOk()
        ->assertJsonPath('data.sources.0.title', 'Q3 plan')
        ->assertJsonPath('data.sources.0.url', 'https://www.notion.so/acme/q3-plan');
});

test('a proposed Notion change opens a pending approval and never writes yet', function () {
    config()->set('okyema.ai.enabled', true);
    config()->set('okyema.notion.databases.REGNO', 'db-regno');

    $this->mock(AIProviderInterface::class, function ($mock) {
        $mock->shouldReceive('generateStructuredResponse')->once()->andReturn([
            'intent' => 'notion_change',
            'answer' => 'I can create that page for you.',
            'sources' => [],
            'notion_change' => ['kind' => 'create', 'title' => 'Q3 plan', 'body' => 'Plan for Q3'],
        ]);
    });

    Http::fake(fn ($request) => Http::response([]));

    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => ConnectorProvider::Notion,
        'status' => ConnectorStatus::Connected,
        'access_token' => 'notion-token',
    ]);

    $this->actingAs($user)->postJson('/api/assistant', [
        'request' => 'Create a page titled Q3 plan',
        'workspace_context_id' => $regno->id,
    ])->assertOk()
        ->assertJsonPath('data.approval.kind', 'notion_page_create')
        ->assertJsonPath('data.approval.summary', 'Q3 plan');

    expect(ApprovalRequest::count())->toBe(1);
    expect(ApprovalRequest::first()->status->value)->toBe('pending');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v1/pages'));
});

test('approving a Notion change executes the write and rejecting cancels it', function () {
    config()->set('okyema.notion.databases.REGNO', 'db-regno');

    Http::fake(function ($request) {
        if ($request->url() === 'https://api.notion.com/v1/databases/db-regno') {
            return Http::response(['properties' => ['Name' => ['type' => 'title']]]);
        }

        if ($request->url() === 'https://api.notion.com/v1/pages') {
            return Http::response(['id' => 'page-1', 'url' => 'https://www.notion.so/acme/page-1']);
        }

        return Http::response([], 404);
    });

    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => ConnectorProvider::Notion,
        'status' => ConnectorStatus::Connected,
        'access_token' => 'notion-token',
    ]);

    $create = ApprovalRequest::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'kind' => 'notion_page_create',
        'title' => 'Create Notion page: Q3 plan',
        'details' => ['workspace_context_id' => $regno->id, 'title' => 'Q3 plan', 'body' => 'Plan', 'page_id' => null],
        'status' => 'pending',
    ]);

    $this->actingAs($user)->postJson("/api/approvals/{$create->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonPath('data.page.url', 'https://www.notion.so/acme/page-1');

    expect($create->fresh()->status->value)->toBe('approved');

    $update = ApprovalRequest::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'kind' => 'notion_page_update',
        'title' => 'Update Notion page: Q3 plan',
        'details' => ['workspace_context_id' => $regno->id, 'title' => 'Q3 plan', 'body' => '', 'page_id' => 'page-1'],
        'status' => 'pending',
    ]);

    $this->actingAs($user)->postJson("/api/approvals/{$update->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

test('a proposed calendar event opens a pending approval and never writes yet', function () {
    config()->set('okyema.ai.enabled', true);

    $this->mock(AIProviderInterface::class, function ($mock) {
        $mock->shouldReceive('generateStructuredResponse')->once()->andReturn([
            'intent' => 'calendar_change',
            'answer' => 'I can add that to Google Calendar.',
            'sources' => [],
            'notion_change' => null,
            'calendar_change' => [
                'kind' => 'create',
                'title' => 'Team sync',
                'starts_at' => '2026-10-08T15:00:00+01:00',
                'ends_at' => '2026-10-08T16:00:00+01:00',
                'timezone' => 'Europe/London',
                'description' => '',
                'location' => '',
            ],
        ]);
    });

    Http::fake(fn ($request) => Http::response([]));

    $user = $this->makeUser();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => ConnectorProvider::Google,
        'status' => ConnectorStatus::Connected,
        'access_token' => 'google-token',
        'external_account_id' => 'john@okyema.test',
    ]);

    $this->actingAs($user)->postJson('/api/assistant', ['request' => 'Add a team sync tomorrow at 3pm'])
        ->assertOk()
        ->assertJsonPath('data.approval.kind', 'calendar_event_create')
        ->assertJsonPath('data.approval.summary', 'Team sync');

    expect(ApprovalRequest::count())->toBe(1);
    expect(ApprovalRequest::first()->status->value)->toBe('pending');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'calendar/v3/calendars/primary/events'));
});

test('approving a calendar event creates it in Google Calendar', function () {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/primary/events' => Http::response([
            'id' => 'evt-1',
            'htmlLink' => 'https://calendar.google.com/event?eid=evt-1',
            'summary' => 'Team sync',
        ]),
    ]);

    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => ConnectorProvider::Google,
        'status' => ConnectorStatus::Connected,
        'access_token' => 'google-token',
        'external_account_id' => 'john@okyema.test',
    ]);

    $approval = ApprovalRequest::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'kind' => 'calendar_event_create',
        'title' => 'Add to Google Calendar: Team sync',
        'details' => [
            'title' => 'Team sync',
            'starts_at' => '2026-10-08T15:00:00+01:00',
            'ends_at' => '2026-10-08T16:00:00+01:00',
            'timezone' => 'Europe/London',
            'description' => '',
            'location' => '',
        ],
        'status' => 'pending',
    ]);

    $this->actingAs($user)->postJson("/api/approvals/{$approval->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.ok', true)
        ->assertJsonPath('data.event.id', 'evt-1');

    expect($approval->fresh()->status->value)->toBe('approved');
});

test('the system prompt instructs the model to emit a notion_change for creates', function () {
    $method = new ReflectionMethod(AssistantService::class, 'systemPrompt');

    $prompt = $method->invoke(app(AssistantService::class));

    expect($prompt)
        ->toContain('notion_change')
        ->toContain('"create"')
        ->toContain('never refuse for lack of context');
});

test('the system prompt instructs the model to emit a calendar_change for events', function () {
    $method = new ReflectionMethod(AssistantService::class, 'systemPrompt');

    $prompt = $method->invoke(app(AssistantService::class));

    expect($prompt)
        ->toContain('calendar_change')
        ->toContain('"create"')
        ->toContain('ISO 8601');
});

test('approving routes the event to the chosen account and calendar', function () {
    Http::fake([
        'https://www.googleapis.com/calendar/v3/calendars/team%40group.calendar.google.com/events' => Http::response([
            'id' => 'evt-2',
            'summary' => 'Offsite',
        ]),
    ]);

    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => ConnectorProvider::Google,
        'status' => ConnectorStatus::Connected,
        'access_token' => 'first-token',
        'external_account_id' => 'first@okyema.test',
    ]);
    $second = ConnectorAccount::create([
        'user_id' => $user->id,
        'provider' => ConnectorProvider::Google,
        'status' => ConnectorStatus::Connected,
        'access_token' => 'second-token',
        'external_account_id' => 'second@okyema.test',
    ]);

    $approval = ApprovalRequest::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'kind' => 'calendar_event_create',
        'title' => 'Add to Team: Offsite',
        'details' => [
            'title' => 'Offsite',
            'starts_at' => '2026-10-08T15:00:00+01:00',
            'ends_at' => '2026-10-08T16:00:00+01:00',
            'timezone' => 'Europe/London',
            'calendar_id' => 'team@group.calendar.google.com',
            'connector_account_id' => $second->id,
        ],
        'status' => 'pending',
    ]);

    $this->actingAs($user)->postJson("/api/approvals/{$approval->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.event.id', 'evt-2');

    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer second-token'));
});
