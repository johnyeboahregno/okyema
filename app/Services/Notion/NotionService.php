<?php

declare(strict_types=1);

namespace App\Services\Notion;

use App\Enums\ConnectorProvider;
use App\Enums\ConnectorStatus;
use App\Models\ConnectorAccount;
use App\Models\User;
use App\Models\WorkspaceContext;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Notion read/write for the assistant.
 *
 * Reads are scoped to the active workspace context via its configured Notion
 * database id (ADR-001). Writes are always performed downstream of an
 * ApprovalRequest by NotionWriteService — this class never writes on its own
 * outside of an explicit, approved action.
 */
final class NotionService
{
    private const BASE = 'https://api.notion.com/v1';

    public function isConnected(User $user): bool
    {
        return $this->token($user) !== null;
    }

    /**
     * The Notion database id configured for this workspace context, or null
     * when the context has no Notion mapping (custom workspaces, or the merged
     * "All contexts" view).
     */
    public function databaseId(?WorkspaceContext $context): ?string
    {
        if ($context === null) {
            return null;
        }

        $id = config('okyema.notion.databases.'.$context->type);

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Search the active context's Notion database for pages matching the query.
     *
     * @return list<array{title: string, url: string}>
     */
    public function search(User $user, ?WorkspaceContext $context, string $query, int $limit = 5): array
    {
        $token = $this->token($user);
        $databaseId = $this->databaseId($context);

        if ($token === null || $databaseId === null || $query === '') {
            return [];
        }

        $response = Http::withToken($token)
            ->withHeaders($this->headers())
            ->post(self::BASE.'/search', [
                'query' => $query,
                'filter' => ['value' => 'page', 'property' => 'object'],
                'page_size' => 20,
            ]);

        if ($response->failed()) {
            return [];
        }

        $hits = [];

        foreach ($response->json('results', []) as $page) {
            if (! is_array($page) || ($page['parent']['database_id'] ?? null) !== $databaseId) {
                continue;
            }

            $title = $this->pageTitle($page);
            $url = trim((string) ($page['url'] ?? ''));

            if ($title === '' || $url === '') {
                continue;
            }

            $hits[] = ['title' => $title, 'url' => $url];

            if (count($hits) >= $limit) {
                break;
            }
        }

        return $hits;
    }

    /**
     * Create a page in the context's database.
     *
     * @return array{id: string, url: string, title: string}
     */
    public function createPage(User $user, ?WorkspaceContext $context, string $title, string $body): array
    {
        $token = $this->tokenOrFail($user);
        $databaseId = $this->databaseIdOrFail($context);
        $titleProperty = $this->titlePropertyForDatabase($token, $databaseId);

        $response = Http::withToken($token)
            ->withHeaders($this->headers())
            ->post(self::BASE.'/pages', [
                'parent' => ['database_id' => $databaseId],
                'properties' => [
                    $titleProperty => ['title' => [['text' => ['content' => $title]]]],
                ],
                'children' => $this->bodyBlocks($body),
            ]);

        $this->assertOk($response, 'Notion create failed');

        return [
            'id' => (string) $response->json('id', ''),
            'url' => (string) $response->json('url', ''),
            'title' => $title,
        ];
    }

    /**
     * Update a page: set its title and append the body as paragraph blocks.
     *
     * @return array{id: string, url: string, title: string}
     */
    public function updatePage(User $user, string $pageId, string $title, string $body): array
    {
        $token = $this->tokenOrFail($user);

        $page = Http::withToken($token)
            ->withHeaders($this->headers())
            ->get(self::BASE.'/pages/'.$pageId);

        $this->assertOk($page, 'Notion page lookup failed');

        $titleProperty = $this->titlePropertyFrom($page->json('properties', []));

        $response = Http::withToken($token)
            ->withHeaders($this->headers())
            ->patch(self::BASE.'/pages/'.$pageId, [
                'properties' => [
                    $titleProperty => ['title' => [['text' => ['content' => $title]]]],
                ],
            ]);

        $this->assertOk($response, 'Notion update failed');

        if (trim($body) !== '') {
            Http::withToken($token)
                ->withHeaders($this->headers())
                ->patch(self::BASE.'/blocks/'.$pageId.'/children', [
                    'children' => $this->bodyBlocks($body),
                ]);
        }

        return [
            'id' => $pageId,
            'url' => (string) $response->json('url', ''),
            'title' => $title,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Notion-Version' => (string) config('okyema.notion.version', '2022-06-28'),
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];
    }

    private function token(User $user): ?string
    {
        $token = ConnectorAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', ConnectorProvider::Notion->value)
            ->where('status', ConnectorStatus::Connected->value)
            ->whereNotNull('access_token')
            ->value('access_token');

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function tokenOrFail(User $user): string
    {
        $token = $this->token($user);

        abort_if($token === null, 422, 'Connect Notion in Settings before making changes.');

        return $token;
    }

    private function databaseIdOrFail(?WorkspaceContext $context): string
    {
        $databaseId = $this->databaseId($context);

        abort_if($databaseId === null, 422, 'Notion is not configured for this workspace.');

        return $databaseId;
    }

    private function titlePropertyForDatabase(string $token, string $databaseId): string
    {
        $response = Http::withToken($token)
            ->withHeaders($this->headers())
            ->get(self::BASE.'/databases/'.$databaseId);

        if ($response->failed()) {
            return 'title';
        }

        return $this->titlePropertyFrom($response->json('properties', []));
    }

    /**
     * Find the property that carries a page's title (type "title") — Notion
     * databases name it "Name", "Title" or something custom, so it is read
     * from the schema rather than hard-coded.
     *
     * @param  array<string, mixed>  $properties
     */
    private function titlePropertyFrom(array $properties): string
    {
        foreach ($properties as $name => $property) {
            if (is_array($property) && ($property['type'] ?? null) === 'title') {
                return (string) $name;
            }
        }

        return 'title';
    }

    private function pageTitle(array $page): string
    {
        foreach ($page['properties'] ?? [] as $property) {
            if (! is_array($property) || ($property['type'] ?? null) !== 'title') {
                continue;
            }

            foreach ($property['title'] ?? [] as $richText) {
                $text = trim((string) ($richText['plain_text'] ?? ''));

                if ($text !== '') {
                    return $text;
                }
            }
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function bodyBlocks(string $body): array
    {
        $lines = array_filter(array_map('trim', preg_split('/\R+/', $body) ?: [$body]));

        return array_map(
            fn (string $line): array => [
                'object' => 'block',
                'type' => 'paragraph',
                'paragraph' => [
                    'rich_text' => [['type' => 'text', 'text' => ['content' => $line]]],
                ],
            ],
            array_values($lines),
        );
    }

    private function assertOk(Response $response, string $prefix): void
    {
        if ($response->failed()) {
            throw new RuntimeException($prefix.': '.mb_substr($response->body(), 0, 400));
        }
    }
}
