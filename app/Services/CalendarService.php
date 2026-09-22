<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use App\Models\WorkspaceContext;
use Carbon\CarbonImmutable;

/**
 * Calendar reads: agenda, timeline and the next meeting, all scoped to a
 * workspace context and presented in the user's timezone. Provider details are
 * never leaked — events are canonical here.
 */
final class CalendarService
{
    public function __construct(
        private readonly CalendarConflicts $conflicts,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function agenda(User $user, WorkspaceContext $context, CarbonImmutable $day, ?string $timezone = null): array
    {
        $tz = $timezone ?: $this->timezone($user);

        $start = $day->timezone($tz)->startOfDay()->utc();
        $end = $day->timezone($tz)->endOfDay()->utc();

        return $this->between($user, $context, $start, $end, $tz);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function timeline(User $user, WorkspaceContext $context, CarbonImmutable $from, CarbonImmutable $to): array
    {
        return $this->between($user, $context, $from->utc(), $to->utc(), $this->timezone($user));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function nextMeeting(User $user, WorkspaceContext $context, ?CarbonImmutable $now = null): ?array
    {
        $now = $now ?? CarbonImmutable::now();
        $tz = $this->timezone($user);

        $event = Event::query()
            ->where('user_id', $user->id)
            ->where('workspace_context_id', $context->id)
            ->where('state', 'confirmed')
            ->where('is_all_day', false)
            ->where('ends_at', '>', $now)
            ->orderBy('starts_at')
            ->with('calendar')
            ->first();

        return $event ? $this->present($event, $tz, $now) : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function between(
        User $user,
        WorkspaceContext $context,
        CarbonImmutable $start,
        CarbonImmutable $end,
        string $tz,
    ): array {
        $events = Event::query()
            ->where('user_id', $user->id)
            ->where('workspace_context_id', $context->id)
            ->where(function ($query) use ($start, $end) {
                $query->where(function ($q) use ($start, $end) {
                    $q->where('starts_at', '>=', $start)->where('starts_at', '<', $end);
                });
            })
            ->where('state', '!=', 'cancelled')
            ->orderBy('is_all_day')
            ->orderBy('starts_at')
            ->with('calendar')
            ->get();

        $conflictIds = $this->conflicts->detect(
            $events->map(fn (Event $event) => [
                'id' => $event->id,
                'starts_at' => CarbonImmutable::parse($event->starts_at),
                'ends_at' => $event->ends_at ? CarbonImmutable::parse($event->ends_at) : null,
            ])->all(),
        );

        return $events
            ->map(fn (Event $event) => $this->present($event, $tz))
            ->map(function (array $payload) use ($conflictIds) {
                $payload['has_conflict'] = in_array($payload['id'], $conflictIds, true);

                return $payload;
            })
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Event $event, string $tz, ?CarbonImmutable $now = null): array
    {
        $starts = CarbonImmutable::parse($event->starts_at)->setTimezone($tz);
        $ends = $event->ends_at ? CarbonImmutable::parse($event->ends_at)->setTimezone($tz) : null;

        return [
            'id' => $event->id,
            'title' => $event->title,
            'location' => $event->location,
            'starts_at' => $event->starts_at->toIso8601String(),
            'ends_at' => $event->ends_at?->toIso8601String(),
            'is_all_day' => $event->is_all_day,
            'state' => $event->state->value,
            'timezone' => $event->timezone ?: $tz,
            'day_label' => $event->is_all_day ? 'All day' : $starts->format('j M'),
            'time_label' => $event->is_all_day ? 'All day' : $starts->format('H:i'),
            'ends_label' => $event->is_all_day || $ends === null ? null : $ends->format('H:i'),
            'starts_in_minutes' => $now ? max(0, (int) $now->diffInMinutes($starts, false) * -1) : null,
            'calendar' => [
                'id' => $event->calendar?->id,
                'name' => $event->calendar?->name,
                'provider' => $event->calendar?->provider->value,
                'colour' => $event->calendar?->colour,
            ],
        ];
    }

    private function timezone(User $user): string
    {
        return $user->profile?->timezone ?: 'UTC';
    }
}
