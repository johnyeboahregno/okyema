<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\Inbox\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
    ) {}

    public function approve(Request $request, ApprovalRequest $approval): JsonResponse
    {
        return response()->json([
            'data' => $this->approvals->approve($request->user(), $approval),
        ]);
    }

    public function reject(Request $request, ApprovalRequest $approval): JsonResponse
    {
        return response()->json([
            'data' => $this->approvals->reject($request->user(), $approval),
        ]);
    }
}
