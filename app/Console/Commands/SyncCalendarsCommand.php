<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ConnectorStatus;
use App\Models\ConnectorAccount;
use App\Services\Connectors\CalendarSyncService;
use App\Services\Connectors\ConnectorRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Pulls every connected calendar so the agenda, timeline and the meetings
 * workspace stay current. Runs on the scheduler (see routes/console.php) and
 * is safe to run by hand: each account syncs idempotently through its cursor.
 */
final class SyncCalendarsCommand extends Command
{
    protected $signature = 'calendar:sync {--account= : restrict the run to a single connector account id}';

    protected $description = 'Sync connected calendars and mirror their entries into meetings';

    public function handle(ConnectorRegistry $registry, CalendarSyncService $sync): int
    {
        $accounts = ConnectorAccount::query()
            ->where('status', ConnectorStatus::Connected->value)
            ->when(
                $this->option('account'),
                fn ($query, $id) => $query->where('id', $id),
            )
            ->get();

        $synced = 0;

        foreach ($accounts as $account) {
            $context = $sync->contextFor($account);

            if ($context === null) {
                $this->warn("Skipping {$account->provider->value} account {$account->id}: no workspace context.");

                continue;
            }

            try {
                $run = $sync->sync($account, $context, $registry->calendar($account->provider));

                $this->line("Synced {$account->provider->value} account {$account->id}: {$run->items_synced} events.");
                $synced++;
            } catch (\Throwable $e) {
                $this->error("Failed {$account->provider->value} account {$account->id}: {$e->getMessage()}");

                Log::warning('calendar:sync failed', [
                    'connector_account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Calendar sync finished: {$synced} of {$accounts->count()} accounts synced.");

        return self::SUCCESS;
    }
}
