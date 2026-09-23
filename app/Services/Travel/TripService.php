<?php

declare(strict_types=1);

namespace App\Services\Travel;

use App\Enums\TravelSegmentType;
use App\Models\TravelSegment;
use App\Models\Trip;
use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * Trips: CRUD and saving a user-confirmed itinerary extraction.
 */
final class TripService
{
    /**
     * @return Collection<int, Trip>
     */
    public function index(User $user, ?WorkspaceContext $context): Collection
    {
        return Trip::query()
            ->forContext($user, $context)
            ->with('segments')
            ->orderBy('starts_on')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $user, WorkspaceContext $context, array $data): Trip
    {
        return Trip::create([
            'user_id' => $user->id,
            'workspace_context_id' => $context->id,
            'title' => $data['title'],
            'purpose' => $data['purpose'] ?? null,
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
        ]);
    }

    /**
     * Save an itinerary the user has confirmed.
     *
     * @param  list<array<string, mixed>>  $segments
     */
    public function saveItinerary(User $user, WorkspaceContext $context, string $title, ?string $startsOn, ?string $endsOn, array $segments): Trip
    {
        $trip = $this->create($user, $context, [
            'title' => $title,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
        ]);

        foreach ($segments as $segment) {
            TravelSegment::create([
                'trip_id' => $trip->id,
                'type' => TravelSegmentType::tryFrom($segment['type'] ?? 'other')?->value ?? 'other',
                'details' => $segment['details'] ?? null,
                'from_location' => $segment['from_location'] ?? null,
                'to_location' => $segment['to_location'] ?? null,
                'starts_at' => $segment['starts_at'] ?? null,
            ]);
        }

        return $trip->load('segments');
    }
}
