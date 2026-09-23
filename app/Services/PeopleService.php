<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Person;
use App\Models\ProviderIdentity;
use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Database\Eloquent\Collection;

/**
 * People and identity reconciliation. Provider identities are attached to a
 * canonical person, and merges are reversible (an identity can be moved).
 */
final class PeopleService
{
    /**
     * @return Collection<int, Person>
     */
    public function index(User $user, ?WorkspaceContext $context): Collection
    {
        return Person::query()
            ->forContext($user, $context)
            ->with('providerIdentities')
            ->orderBy('name')
            ->get();
    }

    /**
     * Link a provider identity to a person, creating the person on first
     * sight and keeping the link idempotent per provider id.
     */
    public function link(User $user, WorkspaceContext $context, string $name, string $provider, ?string $providerId = null, ?string $email = null): Person
    {
        $person = Person::firstOrCreate(
            ['user_id' => $user->id, 'workspace_context_id' => $context->id, 'name' => $name],
        );

        ProviderIdentity::firstOrCreate(
            ['provider' => $provider, 'provider_id' => $providerId],
            ['person_id' => $person->id, 'email' => $email],
        );

        return $person->load('providerIdentities');
    }
}
