<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use RuntimeException;

/**
 * Chat adapter (Slack / Teams) for reading and drafting. Sending is
 * approval-gated upstream and refuses loudly without a stored token.
 */
class ChatConnector
{
    public function capabilities(): array
    {
        return ['chat.read', 'chat.draft', 'chat.send'];
    }

    /**
     * @param  list<string>  $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $body, ?string $token): array
    {
        if ($token === null || $token === '') {
            throw new RuntimeException('Chat is not connected. No access token is stored.');
        }

        throw new RuntimeException('Chat send requires a connected account (chat.send).');
    }
}
