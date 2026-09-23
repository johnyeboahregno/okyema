<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Expense;
use App\Models\User;
use App\Models\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

/**
 * Expense reads: monthly views, missing-receipt detection and CSV export.
 * Money stays in integer minor units throughout.
 */
final class ExpenseService
{
    /**
     * @return array{
     *     month: string,
     *     total_minor: int,
     *     count: int,
     *     expenses: Collection<int, Expense>
     * }
     */
    public function monthly(User $user, ?WorkspaceContext $context, ?string $month = null): array
    {
        $month = $month ?? now()->format('Y-m');
        $start = CarbonImmutable::parse($month.'-01')->startOfMonth();
        $end = $start->endOfMonth();

        $expenses = Expense::query()
            ->forContext($user, $context)
            ->whereBetween('expense_date', [$start->toDateString(), $end->toDateString()])
            ->with('receipts')
            ->orderBy('expense_date')
            ->get();

        return [
            'month' => $month,
            'total_minor' => $expenses->sum('total_minor'),
            'count' => $expenses->count(),
            'expenses' => $expenses,
        ];
    }

    /**
     * Confirmed or submitted expenses with no receipt attached.
     *
     * @return Collection<int, Expense>
     */
    public function missingReceipts(User $user, ?WorkspaceContext $context): Collection
    {
        return Expense::query()
            ->forContext($user, $context)
            ->whereIn('status', ['confirmed', 'submitted'])
            ->whereDoesntHave('receipts')
            ->orderBy('expense_date')
            ->get();
    }

    public function exportCsv(User $user, ?WorkspaceContext $context, ?string $month = null): string
    {
        $monthly = $this->monthly($user, $context, $month);

        $lines = ['Date,Merchant,Category,Currency,Amount,Status,Receipts'];

        foreach ($monthly['expenses'] as $expense) {
            $lines[] = implode(',', [
                $expense->expense_date->toDateString(),
                $this->csv($expense->merchant),
                $this->csv($expense->category ?? ''),
                $expense->currency,
                MoneyMath::minorToMajor($expense->total_minor),
                $expense->status->value,
                $expense->receipts->count(),
            ]);
        }

        return implode("\n", $lines)."\n";
    }

    private function csv(string $value): string
    {
        if (! str_contains($value, ',') && ! str_contains($value, '"')) {
            return $value;
        }

        return '"'.str_replace('"', '""', $value).'"';
    }
}
