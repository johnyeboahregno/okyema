<?php

declare(strict_types=1);

namespace App\Services\Receipts;

use App\Enums\ReceiptFileStatus;
use App\Models\ConnectorAccount;
use App\Models\Expense;
use App\Models\Receipt;
use App\Models\ReceiptExtraction;
use App\Models\User;
use App\Models\WorkspaceContext;
use App\Services\Connectors\DriveFilesConnector;
use App\Services\MoneyMath;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Receipt capture: preserve the original, extract (best-effort), require
 * confirmation, detect duplicates and file into Drive.
 */
final class ReceiptService
{
    public function __construct(
        private readonly ReceiptStorage $storage,
        private readonly ReceiptScanner $scanner,
        private readonly ReceiptNaming $naming,
        private readonly DriveFilesConnector $drive,
    ) {}

    /**
     * @return array{receipt: Receipt, extraction: ?ReceiptExtraction, duplicates: Collection<int, Receipt>}
     */
    public function capture(User $user, WorkspaceContext $context, UploadedFile $file): array
    {
        $stored = $this->storage->store($file);

        $receipt = Receipt::create([
            'user_id' => $user->id,
            'workspace_context_id' => $context->id,
            'original_path' => $stored['path'],
            'content_hash' => $stored['hash'],
            'mime_type' => $stored['mime'],
            'size_bytes' => $stored['size'],
            'file_status' => ReceiptFileStatus::Stored,
        ]);

        $extraction = $this->attemptScan($receipt);

        return [
            'receipt' => $receipt,
            'extraction' => $extraction,
            'duplicates' => $this->duplicates($receipt),
        ];
    }

    /**
     * Confirm the (possibly corrected) fields and create the linked expense.
     *
     * @param  array<string, mixed>  $data
     * @return array{receipt: Receipt, expense: Expense, duplicates: Collection<int, Receipt>, filing: array<string, mixed>}
     */
    public function confirm(User $user, Receipt $receipt, array $data): array
    {
        $total = MoneyMath::majorToMinor((string) $data['total']);

        $receipt->forceFill([
            'merchant' => $data['merchant'],
            'total_minor' => $total,
            'currency' => $data['currency'] ?? config('okyema.currency.code'),
            'expense_date' => $data['expense_date'],
            'confidence' => $data['confidence'] ?? null,
        ])->save();

        $expense = Expense::create([
            'user_id' => $user->id,
            'workspace_context_id' => $receipt->workspace_context_id,
            'merchant' => $receipt->merchant,
            'category' => $data['category'] ?? null,
            'expense_date' => $receipt->expense_date,
            'total_minor' => $total,
            'currency' => $receipt->currency,
            'payment_method' => $data['payment_method'] ?? null,
            'status' => 'confirmed',
        ]);

        $receipt->forceFill(['expense_id' => $expense->id])->save();

        $duplicates = $this->duplicates($receipt);
        $filing = $this->fileToDrive($receipt, $user);

        return [
            'receipt' => $receipt->fresh(),
            'expense' => $expense,
            'duplicates' => $duplicates,
            'filing' => $filing,
        ];
    }

    /**
     * @return Collection<int, Receipt>
     */
    public function index(User $user, WorkspaceContext $context): Collection
    {
        return Receipt::query()
            ->where('user_id', $user->id)
            ->where('workspace_context_id', $context->id)
            ->with(['expense', 'extractions'])
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Duplicate candidates: an identical content hash, or the same
     * merchant/date/amount.
     *
     * @return Collection<int, Receipt>
     */
    public function duplicates(Receipt $receipt): Collection
    {
        return Receipt::query()
            ->where('user_id', $receipt->user_id)
            ->where('id', '!=', $receipt->id)
            ->where(function ($query) use ($receipt) {
                $query->where('content_hash', $receipt->content_hash);

                if ($receipt->merchant !== null && $receipt->expense_date !== null && $receipt->total_minor !== null) {
                    $query->orWhere(function ($q) use ($receipt) {
                        $q->where('merchant', $receipt->merchant)
                            ->where('expense_date', $receipt->expense_date)
                            ->where('total_minor', $receipt->total_minor);
                    });
                }
            })
            ->get();
    }

    /**
     * File a confirmed receipt into its deterministic Drive folder.
     *
     * @return array<string, mixed>
     */
    public function fileToDrive(Receipt $receipt, User $user): array
    {
        if ($receipt->merchant === null || $receipt->expense_date === null || $receipt->total_minor === null) {
            return ['filed' => false, 'reason' => 'Confirm the receipt fields before filing.'];
        }

        $context = $receipt->workspaceContext;
        $account = ConnectorAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', 'google')
            ->whereNotNull('access_token')
            ->first();

        $date = CarbonImmutable::parse($receipt->expense_date);
        $folder = $this->naming->folder($context->type->value, $date);
        $filename = $this->naming->filename(
            $date,
            $receipt->merchant,
            $receipt->currency ?? 'GBP',
            $receipt->total_minor,
            str_pad(dechex($receipt->id), 8, '0', STR_PAD_LEFT),
            pathinfo($receipt->original_path, PATHINFO_EXTENSION) ?: 'bin',
        );

        $receipt->forceFill(['drive_folder' => $folder, 'drive_filename' => $filename])->save();

        if ($account === null) {
            return ['filed' => false, 'reason' => 'Drive not connected — receipt stored locally.', 'folder' => $folder, 'filename' => $filename];
        }

        try {
            $contents = file_get_contents(storage_path('app/'.$receipt->original_path));
            if ($contents === false) {
                throw new InvalidArgumentException('Original receipt file is missing.');
            }

            $uploaded = $this->drive->upload($folder, $filename, $contents, (string) $receipt->mime_type, $account->access_token);

            $receipt->forceFill([
                'file_status' => ReceiptFileStatus::Filed,
                'drive_file_id' => $uploaded['file_id'],
                'drive_link' => $uploaded['link'],
                'filed_at' => now(),
            ])->save();

            return ['filed' => true, 'file_id' => $uploaded['file_id'], 'link' => $uploaded['link']];
        } catch (\Throwable $e) {
            Log::warning('receipt.filing.failed', ['receipt_id' => $receipt->id, 'error' => $e->getMessage()]);

            $receipt->forceFill(['file_status' => ReceiptFileStatus::Failed])->save();

            return ['filed' => false, 'reason' => $e->getMessage(), 'folder' => $folder, 'filename' => $filename];
        }
    }

    private function attemptScan(Receipt $receipt): ?ReceiptExtraction
    {
        $result = $this->scanner->scan(storage_path('app/'.$receipt->original_path), $receipt->mime_type);

        if ($result === null) {
            return null;
        }

        return ReceiptExtraction::create(array_merge(['receipt_id' => $receipt->id], $result));
    }
}
