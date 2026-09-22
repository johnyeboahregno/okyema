<?php

declare(strict_types=1);

namespace App\Services\Connectors;

use App\Models\Calendar;
use App\Models\ConnectorAccount;
use App\Models\Event;
use App\Models\SyncCursor;
use App\Models\SyncRun;
use App\Models\WorkspaceContext;
use App\Services\Connectors\Contracts\CalendarConnector;
use Illuminate\Support\Facades\DB;

/**
 * Runs one calendar sync for a connector account and makes it safe to retry:
 * events are upserted by (provider, provider_event_id), the cursor is
 * persisted, and a SyncRun records the outcome.
 */
final class CalendarSyncService
{
    public function sync(
        ConnectorAccount $account,
        WorkspaceContext $context,
        CalendarConnector $connector,
    ): SyncRun {
        $calendar = $this->primaryCalendar($account, $context);

        $cursor = SyncCursor::query()
            ->where('connector_account_id', $account->id)
            ->where('resource_type', 'events')
            ->value('cursor');

        $startedAt = now();

        try {
            $result = $connector->syncEvents($account, $cursor);

            $synced = DB::transaction(function () use ($account, $context, $calendar, $result): int {
                $count = 0;

                foreach ($result->items as $item) {
                    $this->upsertEvent($account, $context, $calendar, $item);
                    $count++;
                }

                if ($result->nextCursor !== null) {
                    SyncCursor::updateOrCreate(
                        ['connector_account_id' => $account->id, 'resource_type' => 'events'],
                        ['cursor' => $result->nextCursor],
                    );
                }

                $account->forceFill(['last_synced_at' => now()])->save();

                return $count;
            });

            return SyncRun::create([
                'connector_account_id' => $account->id,
                'resource_type' => 'events',
                'status' => 'success',
                'items_synced' => $synced,
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            SyncRun::create([
                'connector_account_id' => $account->id,
                'resource_type' => 'events',
                'status' => 'error',
                'items_synced' => 0,
                'error_message' => $e->getMessage(),
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);

            throw $e;
        }
    }

    private function primaryCalendar(ConnectorAccount $account, WorkspaceContext $context): Calendar
    {
        return Calendar::firstOrCreate(
            [
                'connector_account_id' => $account->id,
                'provider_calendar_id' => 'primary',
            ],
            [
                'user_id' => $account->user_id,
                'workspace_context_id' => $context->id,
                'name' => $account->provider->label().' Calendar',
                'provider' => $account->provider->value,
                'timezone' => 'UTC',
                'is_primary' => true,
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function upsertEvent(
        ConnectorAccount $account,
        WorkspaceContext $context,
        Calendar $calendar,
        array $item,
    ): void {
        Event::updateOrCreate(
            [
                'provider' => $item['provider'] ?? $account->provider->value,
                'provider_event_id' => $item['provider_event_id'] ?? null,
            ],
            [
                'calendar_id' => $calendar->id,
                'user_id' => $account->user_id,
                'workspace_context_id' => $context->id,
                'title' => $item['title'],
                'description' => $item['description'] ?? null,
                'location' => $item['location'] ?? null,
                'starts_at' => $item['starts_at'],
                'ends_at' => $item['ends_at'],
                'is_all_day' => $item['is_all_day'] ?? false,
                'state' => $item['state'] ?? 'confirmed',
                'timezone' => $item['timezone'] ?? null,
                'provider_revision' => $item['provider_revision'] ?? null,
                'external_url' => $item['external_url'] ?? null,
                'recurrence_rule' => $item['recurrence_rule'] ?? null,
                'recurrence_id' => $item['recurrence_id'] ?? null,
            ],
        );
    }
}
