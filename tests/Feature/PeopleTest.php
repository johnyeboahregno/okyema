<?php

declare(strict_types=1);

use App\Models\Person;
use App\Models\WorkspaceContext;
use App\Services\PeopleService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('people can be listed with their provider identities', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    app(PeopleService::class)->link($user, $regno, 'Ama Mensah', 'gmail', 'g-1', 'ama@regno.test');

    $this->actingAs($user)->getJson('/api/people')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Ama Mensah')
        ->assertJsonPath('data.0.identities.0.email', 'ama@regno.test');
});

test('linking the same provider id twice is idempotent', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    $service = app(PeopleService::class);
    $service->link($user, $regno, 'Ama Mensah', 'gmail', 'g-1', 'ama@regno.test');
    $service->link($user, $regno, 'Ama Mensah', 'gmail', 'g-1', 'ama@regno.test');

    expect(Person::count())->toBe(1);
    expect($service->index($user, $regno)->first()->providerIdentities)->toHaveCount(1);
});
