<?php

declare(strict_types=1);

namespace App\Services\Notion;

use App\Models\ApprovalRequest;
use App\Models\User;
use App\Models\WorkspaceContext;
use RuntimeException;

/**
 * Executes an approved Notion change. Called only from the approval flow,
 * after the user explicitly approves the pending ApprovalRequest.
 */
final class NotionWriteService
{
    public function __construct(
        private readonly NotionService $notion,
    ) {}

    /**
     * @return array{id: string, url: string, title: string}
     */
    public function execute(User $user, ApprovalRequest $approval): array
    {
        $details = $approval->details ?? [];

        $context = WorkspaceContext::find($details['workspace_context_id'] ?? null);

        $title = trim((string) ($details['title'] ?? ''));
        $body = trim((string) ($details['body'] ?? ''));

        return match ($approval->kind) {
            'notion_page_create' => $this->notion->createPage($user, $context, $title, $body),
            'notion_page_update' => $this->notion->updatePage($user, (string) ($details['page_id'] ?? ''), $title, $body),
            default => throw new RuntimeException('Unknown Notion approval kind: '.$approval->kind),
        };
    }
}
