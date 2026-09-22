<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActionItem;
use App\Models\AutomationRule;
use App\Models\AutomationRun;
use App\Models\User;
use App\Models\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Deterministic, user-controlled rule engine. Each run is recorded with its
 * outcome, and every rule has a pause control (is_active). AI may enrich a
 * result later but never becomes an unbounded workflow engine.
 */
final class RuleEngine
{
    public function __construct(
        private readonly ActionService $actions,
        private readonly BriefingService $briefing,
    ) {}

    public function run(AutomationRule $rule, User $user): AutomationRun
    {
        $startedAt = now();

        try {
            $result = $this->evaluate($rule, $user);

            $rule->forceFill(['last_run_at' => now()])->save();

            return AutomationRun::create([
                'automation_rule_id' => $rule->id,
                'status' => 'success',
                'result' => $result,
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);
        } catch (\Throwable $e) {
            return AutomationRun::create([
                'automation_rule_id' => $rule->id,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'started_at' => $startedAt,
                'finished_at' => now(),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function evaluate(AutomationRule $rule, User $user): array
    {
        $context = $rule->workspaceContext;

        return match ($rule->trigger) {
            'action_due_soon' => $this->dueSoon($user, $context, (int) ($rule->conditions['days'] ?? 1)),
            'morning_briefing' => $this->briefing->morning($user, $context),
            'weekly_review' => $this->briefing->weekly($user, $context),
            default => ['note' => "No handler for trigger [{$rule->trigger}]."],
        };
    }

    /**
     * @return array{actions: list<string>, count: int}
     */
    private function dueSoon(User $user, WorkspaceContext $context, int $days): array
    {
        $horizon = CarbonImmutable::now()->startOfDay()->addDays($days)->endOfDay();

        /** @var Collection<int, ActionItem> $actions */
        $actions = $this->actions->index($user, $context, 'inbox')
            ->filter(fn ($action) => $action->due_date !== null && $action->due_date->lte($horizon->toDateString()));

        return [
            'actions' => $actions->map(fn ($action) => $action->title)->values()->all(),
            'count' => $actions->count(),
        ];
    }
}
