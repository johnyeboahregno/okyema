<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Receipt;
use App\Services\Receipts\ReceiptService;
use App\Services\WorkspaceContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReceiptController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        private readonly ReceiptService $receipts,
        private readonly WorkspaceContextService $workspaces,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $context = $this->workspaces->activeContext($request->user());

        return response()->json([
            'data' => $this->receipts->index($request->user(), $context)->map(fn (Receipt $receipt) => [
                'id' => $receipt->id,
                'merchant' => $receipt->merchant,
                'total_minor' => $receipt->total_minor,
                'currency' => $receipt->currency,
                'expense_date' => $receipt->expense_date?->toDateString(),
                'file_status' => $receipt->file_status->value,
                'drive_link' => $receipt->drive_link,
                'expense_id' => $receipt->expense_id,
                'confidence' => $receipt->confidence,
                'has_extraction' => $receipt->extractions->isNotEmpty(),
            ]),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'receipt' => ['required', 'file', 'max:'.config('okyema.receipts.max_kb')],
        ]);

        $result = $this->receipts->capture(
            $request->user(),
            $this->workspaces->activeContext($request->user()),
            $request->file('receipt'),
        );

        return response()->json([
            'data' => [
                'receipt' => $result['receipt'],
                'extraction' => $result['extraction'],
                'duplicates' => $result['duplicates'],
            ],
        ], 201);
    }

    public function confirm(Request $request, Receipt $receipt): JsonResponse
    {
        $this->authorizeModel($receipt);

        $validated = $request->validate([
            'merchant' => ['required', 'string', 'max:255'],
            'total' => ['required', 'string', 'regex:/^\d{1,9}(\.\d{1,2})?$/'],
            'expense_date' => ['required', 'date'],
            'currency' => ['sometimes', 'nullable', 'string', 'size:3'],
            'category' => ['sometimes', 'nullable', 'string', 'max:255'],
            'payment_method' => ['sometimes', 'nullable', 'string', 'max:255'],
            'confidence' => ['sometimes', 'nullable', 'numeric'],
        ]);

        return response()->json([
            'data' => $this->receipts->confirm($request->user(), $receipt, $validated),
        ]);
    }
}
