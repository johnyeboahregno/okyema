<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Services\Inbox\ApprovalService;
use App\Services\Notion\NotionApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalController extends Controller
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly NotionApprovalService $notionApprovals,
    ) {}

    public function approve(Request $request, ApprovalRequest $approval): JsonResponse
    {
        $data = $this->isNotion($approval)
            ? $this->notionApprovals->approve($request->user(), $approval)
            : $this->approvals->approve($request->user(), $approval);

        return response()->json(['data' => $data]);
    }

    public function reject(Request $request, ApprovalRequest $approval): JsonResponse
    {
        $data = $this->isNotion($approval)
            ? $this->notionApprovals->reject($request->user(), $approval)
            : $this->approvals->reject($request->user(), $approval);

        return response()->json(['data' => $data]);
    }

    private function isNotion(ApprovalRequest $approval): bool
    {
        return str_starts_with((string) $approval->kind, 'notion_page_');
    }
}
