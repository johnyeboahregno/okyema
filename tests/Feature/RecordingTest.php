<?php

declare(strict_types=1);

use App\Models\Meeting;
use App\Models\RecordingJob;
use App\Services\AI\AIProviderInterface;
use App\Services\WorkspaceContextService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'okyema.transcription.provider' => 'assemblyai',
        'okyema.transcription.api_key' => 'test-key',
        'okyema.transcription.webhook_secret' => 'test-secret',
    ]);
});

test('recording upload requires authentication', function () {
    $this->postJson('/api/recordings', [])
        ->assertUnauthorized();
});

test('recording upload submits to AssemblyAI and tracks the job', function () {
    Http::fake([
        'https://api.assemblyai.com/v2/upload' => Http::response(['upload_url' => 'https://cdn.assemblyai.com/abc']),
        'https://api.assemblyai.com/v2/transcript' => Http::response(['id' => 'tr-123', 'status' => 'queued']),
    ]);

    $user = $this->makeUser();

    $response = $this->actingAs($user)->postJson('/api/recordings', [
        'title' => 'Team sync',
        'audio' => UploadedFile::fake()->create('meeting.webm', 100, 'audio/webm'),
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', 'queued');

    expect(RecordingJob::count())->toBe(1);
    expect(RecordingJob::first()->provider_job_id)->toBe('tr-123');

    Http::assertSent(fn ($request) => $request->url() === 'https://api.assemblyai.com/v2/transcript'
        && $request['audio_url'] === 'https://cdn.assemblyai.com/abc'
        && $request['webhook_auth_header_value'] === 'test-secret');
});

test('the assemblyai webhook files the transcript as a meeting note', function () {
    $user = $this->makeUser();
    $context = app(WorkspaceContextService::class)->activeContextOrFail($user);

    $job = RecordingJob::create([
        'user_id' => $user->id,
        'workspace_context_id' => $context->id,
        'title' => 'Team sync',
        'provider' => 'assemblyai',
        'provider_job_id' => 'tr-123',
        'status' => 'queued',
    ]);

    $this->postJson('/api/webhooks/assemblyai', [
        'transcript_id' => 'tr-123',
        'status' => 'completed',
        'text' => 'We agreed to ship the widget by Friday.',
    ], ['X-Okyema-Webhook-Secret' => 'test-secret'])
        ->assertOk();

    expect($job->fresh()->status)->toBe('completed');

    $meeting = Meeting::where('title', 'Team sync')->firstOrFail();
    expect($meeting->notes()->count())->toBe(1);
    expect($meeting->notes()->first()->body)->toBe('We agreed to ship the widget by Friday.');
    expect($meeting->notes()->first()->source_type->value)->toBe('transcript');
});

test('the assemblyai webhook rejects a bad secret', function () {
    $this->postJson('/api/webhooks/assemblyai', [
        'transcript_id' => 'tr-123',
        'status' => 'completed',
        'text' => 'nope',
    ], ['X-Okyema-Webhook-Secret' => 'wrong'])
        ->assertUnauthorized();
});

test('transcript upload requires authentication', function () {
    $this->postJson('/api/transcripts', [])
        ->assertUnauthorized();
});

test('a transcript is filed as a meeting with a transcript note', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->postJson('/api/transcripts', [
        'title' => 'Team sync',
        'transcript' => 'We decided to ship on Friday.',
    ])->assertCreated()
        ->assertJsonPath('data.title', 'Team sync');

    $meeting = Meeting::where('title', 'Team sync')->firstOrFail();
    expect($meeting->notes()->count())->toBe(1);
    expect($meeting->notes()->first()->body)->toBe('We decided to ship on Friday.');
    expect($meeting->notes()->first()->source_type->value)->toBe('transcript');
});

test('transcripts are listed newest first with their recording time', function () {
    $user = $this->makeUser();
    $starts = now()->subMinutes(10)->toIso8601String();

    $this->actingAs($user)->postJson('/api/transcripts', [
        'title' => 'Team sync',
        'transcript' => 'We decided to ship on Friday.',
        'starts_at' => $starts,
        'ends_at' => now()->toIso8601String(),
    ])->assertCreated();

    $this->actingAs($user)->getJson('/api/transcripts')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Team sync')
        ->assertJsonPath('data.0.transcript', 'We decided to ship on Friday.')
        ->assertJsonPath('data.0.starts_at', $starts);
});

test('a transcript is formatted into an html document by the ai', function () {
    config()->set('okyema.ai.enabled', true);

    $this->mock(AIProviderInterface::class, function ($mock) {
        $mock->shouldReceive('generateStructuredResponse')->once()->andReturn([
            'html' => '<h1>Team sync</h1><p>We decided to ship on Friday.</p>',
        ]);
    });

    $user = $this->makeUser();

    $this->actingAs($user)->postJson('/api/transcripts', [
        'title' => 'Team sync',
        'transcript' => 'We decided to ship on Friday.',
    ])->assertCreated();

    $meeting = Meeting::where('title', 'Team sync')->firstOrFail();
    expect($meeting->formatted_html)->toContain('<h1>Team sync</h1>');

    $this->actingAs($user)->getJson('/api/transcripts')
        ->assertJsonPath('data.0.formatted_html', '<h1>Team sync</h1><p>We decided to ship on Friday.</p>');
});

test('a transcript can be deleted by its owner', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->postJson('/api/transcripts', [
        'title' => 'Team sync',
        'transcript' => 'We decided to ship on Friday.',
    ])->assertCreated();

    $meeting = Meeting::where('title', 'Team sync')->firstOrFail();

    $this->actingAs($user)->deleteJson("/api/transcripts/{$meeting->id}")
        ->assertNoContent();

    expect(Meeting::where('title', 'Team sync')->exists())->toBeFalse();
});

test('a transcript cannot be deleted by another user', function () {
    $owner = $this->makeUser();
    $other = $this->makeUser(['email' => 'other@example.com']);

    $this->actingAs($owner)->postJson('/api/transcripts', [
        'title' => 'Team sync',
        'transcript' => 'We decided to ship on Friday.',
    ])->assertCreated();

    $meeting = Meeting::where('title', 'Team sync')->firstOrFail();

    $this->actingAs($other)->deleteJson("/api/transcripts/{$meeting->id}")
        ->assertForbidden();
});
