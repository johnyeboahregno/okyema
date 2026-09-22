<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use RuntimeException;

/**
 * Email adapter (Gmail / Outlook) for reading and drafting. Sending is
 * approval-gated upstream and refuses loudly without a stored token.
 */
class MailConnector
{
    public function capabilities(): array
    {
        return ['mail.read', 'mail.draft', 'mail.send'];
    }

    /**
     * @param  list<string>  $recipients
     * @return array{message_id: string}
     */
    public function send(array $recipients, string $subject, string $body, ?string $token): array
    {
        if ($token === null || $token === '') {
            throw new RuntimeException('Mail is not connected. No access token is stored.');
        }

        // A real implementation sends via the provider and returns the
        // provider message id. Kept as a boundary so domain code never sees
        // the provider SDK.
        throw new RuntimeException('Mail send requires a connected account (mail.send).');
    }
}
