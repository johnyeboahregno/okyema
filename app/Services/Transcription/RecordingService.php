<?php

declare(strict_types=1);

namespace App\Services\Transcription;

use App\Enums\NoteSourceType;
use App\Models\Meeting;
use App\Models\Note;
use App\Models\RecordingJob;
use App\Models\User;
use App\Models\WorkspaceContext;
use App\Services\AI\AIProviderInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Orchestrates meeting recordings: submits the audio for transcription and,
 * when the provider's webhook returns, files the transcript as a Meeting with
 * a transcript Note so the existing summary pipeline can pick it up.
 */
final class RecordingService
{
    public function __construct(
        private readonly AssemblyAiTranscriber $transcriber,
        private readonly AIProviderInterface $ai,
    ) {}

    public function start(User $user, WorkspaceContext $context, string $title, string $contents, string $mime, string $webhookUrl): RecordingJob
    {
        if (! $this->transcriber->isConfigured()) {
            throw new RuntimeException('Meeting transcription is not configured (ASSEMBLYAI_API_KEY).');
        }

        $submitted = $this->transcriber->submit(
            $contents,
            $mime,
            $webhookUrl,
            (string) config('okyema.transcription.webhook_secret', ''),
        );

        return RecordingJob::create([
            'user_id' => $user->id,
            'workspace_context_id' => $context->id,
            'title' => trim($title),
            'provider' => (string) config('okyema.transcription.provider', 'assemblyai'),
            'provider_job_id' => $submitted['job_id'],
            'status' => $submitted['status'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function complete(array $payload): ?RecordingJob
    {
        $job = RecordingJob::query()
            ->where('provider', (string) config('okyema.transcription.provider', 'assemblyai'))
            ->where('provider_job_id', (string) ($payload['transcript_id'] ?? ''))
            ->first();

        if ($job === null) {
            return null;
        }

        $status = (string) ($payload['status'] ?? '');

        if ($status === 'completed') {
            $text = trim((string) ($payload['text'] ?? ''));
            $this->fileTranscript($job, $text);

            $job->forceFill(['status' => 'completed', 'transcript' => $text])->save();
        } elseif ($status === 'error') {
            $job->forceFill(['status' => 'failed', 'error' => (string) ($payload['error'] ?? 'Transcription failed.')])->save();
        }

        return $job;
    }

    public function saveTranscript(
        User $user,
        WorkspaceContext $context,
        string $title,
        string $transcript,
        ?string $startsAt = null,
        ?string $endsAt = null,
    ): Meeting {
        $meeting = Meeting::create([
            'user_id' => $user->id,
            'workspace_context_id' => $context->id,
            'title' => trim($title) !== '' ? trim($title) : 'Meeting recording',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        $text = trim($transcript);
        if ($text !== '') {
            Note::create([
                'meeting_id' => $meeting->id,
                'user_id' => $user->id,
                'workspace_context_id' => $context->id,
                'body' => $text,
                'source_type' => NoteSourceType::Transcript->value,
            ]);

            $formatted = $this->formatTranscript($text);
            if ($formatted !== null) {
                $meeting->forceFill(['formatted_html' => $formatted])->save();
            }
        }

        return $meeting;
    }

    /**
     * Ask the AI provider to render the transcript as a clean HTML document.
     * Returns null when AI is disabled or the call fails, so the raw transcript
     * remains the fallback.
     */
    public function formatTranscript(string $transcript): ?string
    {
        if (! config('okyema.ai.enabled')) {
            return null;
        }

        try {
            $result = $this->ai->generateStructuredResponse(
                'You format meeting transcripts into a clean, semantic HTML document fragment. '
                .'Start with an h1 title, add a short summary paragraph, then use h2 sections with paragraphs and bullet lists to capture decisions, action items and key points. '
                .'Keep the speaker\'s original wording and never invent facts that are not in the transcript. '
                .'Return a single JSON object with a single key "html" whose value is the complete HTML fragment (no DOCTYPE and no html/body tags).',
                ['transcript' => $transcript],
                [
                    'type' => 'object',
                    'required' => ['html'],
                    'properties' => ['html' => ['type' => 'string']],
                ],
            );

            $html = trim((string) ($result['html'] ?? ''));

            return $html !== '' ? $html : null;
        } catch (\Throwable $e) {
            Log::warning('ai.transcript_format.failed', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Recorded meetings with their transcript note, newest first.
     *
     * @return list<array{id: int, title: string, starts_at: ?string, ends_at: ?string, created_at: ?string, transcript: string, formatted_html: ?string}>
     */
    public function transcripts(User $user, ?WorkspaceContext $context): array
    {
        return Meeting::query()
            ->forContext($user, $context)
            ->whereHas('notes', fn ($query) => $query->where('source_type', NoteSourceType::Transcript->value))
            ->with(['notes' => fn ($query) => $query->where('source_type', NoteSourceType::Transcript->value)->orderByDesc('id')])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Meeting $meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->title,
                'starts_at' => $meeting->starts_at?->toIso8601String(),
                'ends_at' => $meeting->ends_at?->toIso8601String(),
                'created_at' => $meeting->created_at?->toIso8601String(),
                'transcript' => $meeting->notes->first()?->body ?? '',
                'formatted_html' => $meeting->formatted_html,
            ])
            ->values()
            ->all();
    }

    private function fileTranscript(RecordingJob $job, string $text): void
    {
        if ($text === '') {
            return;
        }

        $meeting = Meeting::create([
            'user_id' => $job->user_id,
            'workspace_context_id' => $job->workspace_context_id,
            'title' => $job->title,
        ]);

        Note::create([
            'meeting_id' => $meeting->id,
            'user_id' => $job->user_id,
            'workspace_context_id' => $job->workspace_context_id,
            'body' => $text,
            'source_type' => NoteSourceType::Transcript->value,
        ]);
    }
}
