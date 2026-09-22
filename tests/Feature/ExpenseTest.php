<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\WorkspaceContext;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('monthly expenses sum in integer minor units', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    Expense::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'merchant' => 'Costa Coffee',
        'expense_date' => '2026-09-22',
        'total_minor' => 345,
        'currency' => 'GBP',
        'status' => 'confirmed',
    ]);
    Expense::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'merchant' => 'Trainline',
        'expense_date' => '2026-09-23',
        'total_minor' => 1250,
        'currency' => 'GBP',
        'status' => 'confirmed',
    ]);

    $this->actingAs($user)->getJson('/api/expenses?month=2026-09')
        ->assertOk()
        ->assertJsonPath('data.count', 2)
        ->assertJsonPath('data.total_minor', 1595);
});

test('missing-receipt detection flags confirmed expenses with no receipt', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    Expense::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'merchant' => 'Uber',
        'expense_date' => '2026-09-20',
        'total_minor' => 800,
        'currency' => 'GBP',
        'status' => 'confirmed',
    ]);

    $this->actingAs($user)->getJson('/api/expenses?month=2026-09')
        ->assertOk()
        ->assertJsonPath('data.missing_receipts', 1);
});

test('expenses export to CSV', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    Expense::create([
        'user_id' => $user->id,
        'workspace_context_id' => $regno->id,
        'merchant' => 'Costa Coffee',
        'expense_date' => '2026-09-22',
        'total_minor' => 345,
        'currency' => 'GBP',
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get('/api/expenses/export?month=2026-09');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())->toContain('Costa Coffee');
    expect($response->streamedContent())->toContain('3.45');
});
