<?php

declare(strict_types=1);

use App\Models\Expense;
use App\Models\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;

uses(RefreshDatabase::class);

test('capturing a receipt preserves the original and computes a content hash', function () {
    $user = $this->makeUser();

    $response = $this->actingAs($user)->post('/api/receipts', [
        'receipt' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
    ]);

    $response->assertCreated();

    $receipt = $response->json('data.receipt');

    expect($receipt['content_hash'])->toMatch('/^[a-f0-9]{64}$/');
    expect($receipt['file_status'])->toBe('stored');
    expect($receipt['original_path'])->not->toBeNull();
    expect($response->json('data.extraction'))->toBeNull(); // AI disabled in tests
});

test('confirming a receipt creates an expense in integer minor units and files into the right folder', function () {
    $user = $this->makeUser();

    $receipt = $this->actingAs($user)->post('/api/receipts', [
        'receipt' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
    ])->json('data.receipt');

    $confirmed = $this->actingAs($user)->postJson("/api/receipts/{$receipt['id']}/confirm", [
        'merchant' => 'Costa Coffee',
        'total' => '3.45',
        'expense_date' => '2026-09-22',
    ])->assertOk()->json('data');

    expect($confirmed['expense']['total_minor'])->toBe(345);
    expect($confirmed['expense']['merchant'])->toBe('Costa Coffee');
    expect(Expense::count())->toBe(1);

    // Drive is not connected in tests, so filing reports the pending state.
    expect($confirmed['filing']['filed'])->toBeFalse();
    expect($confirmed['filing']['folder'])->toBe('Business Receipts/REGNO/2026/2026-09');
    expect($confirmed['filing']['filename'])->toStartWith('2026-09-22_costa-coffee_GBP_345_');
});

test('duplicate receipts are detected by content hash', function () {
    $user = $this->makeUser();
    $file = UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg');

    $first = $this->actingAs($user)->post('/api/receipts', ['receipt' => $file])->json('data.receipt');

    $this->actingAs($user)->postJson("/api/receipts/{$first['id']}/confirm", [
        'merchant' => 'Costa Coffee',
        'total' => '3.45',
        'expense_date' => '2026-09-22',
    ]);

    $second = $this->actingAs($user)->post('/api/receipts', ['receipt' => $file])->json('data.receipt');

    $confirmed = $this->actingAs($user)->postJson("/api/receipts/{$second['id']}/confirm", [
        'merchant' => 'Costa Coffee',
        'total' => '3.45',
        'expense_date' => '2026-09-22',
    ])->json('data');

    expect($confirmed['duplicates'])->toHaveCount(1);
    expect($confirmed['duplicates'][0]['id'])->toBe($first['id']);
});

test('an unconfirmed receipt counts as unprocessed on the dashboard', function () {
    $user = $this->makeUser();

    $this->actingAs($user)->post('/api/receipts', [
        'receipt' => UploadedFile::fake()->create('receipt.jpg', 100, 'image/jpeg'),
    ]);

    $this->actingAs($user)->getJson('/api/dashboard')
        ->assertOk()
        ->assertJsonPath('data.unprocessed_receipts', 1);
});

test('confirming with a missing merchant is rejected', function () {
    $user = $this->makeUser();
    $receipt = Receipt::create([
        'user_id' => $user->id,
        'workspace_context_id' => $user->workspaceContexts()->firstOrFail()->id,
        'original_path' => 'receipts/2026/09/hash.jpg',
        'content_hash' => str_repeat('a', 64),
    ]);

    $this->actingAs($user)->postJson("/api/receipts/{$receipt->id}/confirm", [
        'total' => '3.45',
        'expense_date' => '2026-09-22',
    ])->assertUnprocessable();
});
