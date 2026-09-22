<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Profile;
use App\Models\User;
use App\Services\WorkspaceContextService;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Create a signed-in user with a profile and the default workspace
     * contexts, mirroring what registration produces.
     */
    protected function makeUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        Profile::create([
            'user_id' => $user->id,
            'display_name' => $user->name,
            'currency' => config('okyema.currency.code'),
        ]);

        app(WorkspaceContextService::class)->seedDefaultsFor($user);

        return $user;
    }
}
