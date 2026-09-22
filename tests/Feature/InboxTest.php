<?php

declare(strict_types=1);

use App\Models\Conversation;
use App\Models\Draft;
use App\Models\MessageReference;
use App\Models\Person;
use App\Models\WorkspaceContext;
use App\Services\Connectors\MailConnector;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function inboxPersonAndConversation($user, WorkspaceContext $context): array
{
    $person = Person::create([
        'user_id' => $user->id,
        'workspace_context_id' => $context->id,
        'name' => 'Ama Mensah',
    ]);

    $conversation = Conversation::create([
        'user_id' => $user->id,
        'workspace_context_id' => $context->id,
        'person_id' => $person->id,
        'channel' => 'email',
        'subject' => 'Q3 numbers',
        'provider' => 'gmail',
        'last_message_at' => now(),
    ]);

    MessageReference::create([
        'conversation_id' => $conversation->id,
        'user_id' => $user->id,
        'workspace_context_id' => $context->id,
        'direction' => 'inbound',
        'channel' => 'email',
        'snippet' => 'Can you share the Q3 numbers?',
        'sender_person_id' => $person->id,
        'provider' => 'gmail',
        'provider_message_id' => 'm-1',
        'received_at' => now(),
    ]);

    return [$person, $conversation];
}

test('the inbox flags conversations that need a reply', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    inboxPersonAndConversation($user, $regno);

    $this->actingAs($user)->getJson('/api/inbox')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.needs_reply', true);
});

test('drafting a reply opens a pending approval request and never sends immediately', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    [, $conversation] = inboxPersonAndConversation($user, $regno);

    $draft = $this->actingAs($user)->postJson("/api/inbox/{$conversation->id}/draft", [
        'body' => 'Here are the numbers.',
        'subject' => 'Re: Q3 numbers',
    ])->assertCreated()->json('data');

    expect($draft['status'])->toBe('draft');
    expect($draft['approval_request']['status'])->toBe('pending');
    expect(Draft::count())->toBe(1);
});

test('approving a reply without a connected account reports unsent', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    [, $conversation] = inboxPersonAndConversation($user, $regno);
    $draft = $this->actingAs($user)->postJson("/api/inbox/{$conversation->id}/draft", [
        'body' => 'Here are the numbers.',
    ])->json('data');

    $result = $this->actingAs($user)->postJson("/api/approvals/{$draft['approval_request']['id']}/approve")
        ->assertOk()->json('data');

    expect($result['sent'])->toBeFalse();
    expect($result['reason'])->toContain('not connected');
    expect($result['draft']['status'])->toBe('approved');
});

test('approving a reply sends it through the mail connector', function () {
    $this->mock(MailConnector::class, function ($mock) {
        $mock->shouldReceive('send')->once()->andReturn(['message_id' => 'msg-1']);
    });

    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    [, $conversation] = inboxPersonAndConversation($user, $regno);
    $draft = $this->actingAs($user)->postJson("/api/inbox/{$conversation->id}/draft", [
        'body' => 'Here are the numbers.',
    ])->json('data');

    $result = $this->actingAs($user)->postJson("/api/approvals/{$draft['approval_request']['id']}/approve")
        ->assertOk()->json('data');

    expect($result['sent'])->toBeTrue();
    expect($result['draft']['status'])->toBe('sent');
});

test('rejecting a reply marks the draft rejected', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    [, $conversation] = inboxPersonAndConversation($user, $regno);
    $draft = $this->actingAs($user)->postJson("/api/inbox/{$conversation->id}/draft", [
        'body' => 'Here are the numbers.',
    ])->json('data');

    $this->actingAs($user)->postJson("/api/approvals/{$draft['approval_request']['id']}/reject")
        ->assertOk()->assertJsonPath('data.status', 'rejected');

    expect(Draft::first()->status->value)->toBe('rejected');
});

test('an approval cannot be decided twice', function () {
    $user = $this->makeUser();
    $regno = WorkspaceContext::where('type', 'REGNO')->firstOrFail();

    [, $conversation] = inboxPersonAndConversation($user, $regno);
    $draft = $this->actingAs($user)->postJson("/api/inbox/{$conversation->id}/draft", ['body' => 'x'])->json('data');
    $approvalId = $draft['approval_request']['id'];

    $this->actingAs($user)->postJson("/api/approvals/{$approvalId}/reject")->assertOk();
    $this->actingAs($user)->postJson("/api/approvals/{$approvalId}/approve")->assertUnprocessable();
});
