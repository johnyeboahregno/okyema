<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExpenseService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly ExpenseService $expenses,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $context = $this->workspaces->activeContext($user);
        $month = $request->query('month');
        $monthly = $this->expenses->monthly($user, $context, is_string($month) ? $month : null);

        return response()->json([
            'data' => [
                'month' => $monthly['month'],
                'total_minor' => $monthly['total_minor'],
                'count' => $monthly['count'],
                'expenses' => $monthly['expenses']->map(fn ($expense) => [
                    'id' => $expense->id,
                    'merchant' => $expense->merchant,
                    'category' => $expense->category,
                    'expense_date' => $expense->expense_date->toDateString(),
                    'total_minor' => $expense->total_minor,
                    'currency' => $expense->currency,
                    'status' => $expense->status->value,
                    'receipt_count' => $expense->receipts->count(),
                ]),
                'missing_receipts' => $this->expenses->missingReceipts($user, $context)->count(),
            ],
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        $context = $this->workspaces->activeContext($user);
        $month = $request->query('month');
        $csv = $this->expenses->exportCsv($user, $context, is_string($month) ? $month : null);

        return response()->streamDownload(
            fn () => print ($csv),
            sprintf('expenses-%s.csv', is_string($month) ? $month : now()->format('Y-m')),
            ['Content-Type' => 'text/csv'],
        );
    }
}
