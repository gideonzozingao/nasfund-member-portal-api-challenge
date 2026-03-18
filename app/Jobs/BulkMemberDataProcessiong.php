<?php

namespace App\Jobs;

use App\Actions\CreateMemberAction;
use App\Models\BulkUploadBatch;
use App\Utils\CsvParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BulkMemberDataProcessiong implements ShouldQueue
{
    use Queueable, InteractsWithQueue, SerializesModels;

    /**
     * Retry up to 3 times if the job throws an unexpected exception.
     * Row-level failures are handled gracefully and do NOT trigger retries.
     */
    public int $tries = 3;

    /**
     * Timeout in seconds — 10 min covers very large CSVs.
     */
    public int $timeout = 600;

    /**
     * Back-off strategy between retries (seconds).
     */
    public array $backoff = [30, 60, 120];

    // ── Constructor ────────────────────────────────────────────

    /**
     * We pass the batch_id (string) rather than the model itself so that
     * the serialized job payload stays small and re-fetches a fresh model
     * when the worker picks it up (avoids stale data after queue delay).
     */
    public function __construct(private readonly string $batchId) {}

    // ── Handle ─────────────────────────────────────────────────

    public function handle(CsvParser $parser, CreateMemberAction $createAction): void
    {
        // 1. Load the batch record
        $batch = BulkUploadBatch::where('batch_id', $this->batchId)->firstOrFail();

        Log::info('BulkMemberDataProcessiong: job started', [
            'batch_id' => $this->batchId,
            'file'     => $batch->original_filename,
        ]);

        // 2. Mark as processing
        $batch->update([
            'status'     => 'processing',
            'started_at' => now(),
        ]);

        // 3. Retrieve the stored CSV from disk
        $localPath = Storage::path($batch->file_path);

        if (! file_exists($localPath)) {
            $this->markFailed($batch, "CSV file not found on disk: {$batch->file_path}");
            return;
        }

        // 4. Parse rows
        $rows = $parser->parseFromPath($localPath);

        $batch->update(['total_rows' => $rows->count()]);

        // 5. Process row-by-row — partial success is the goal
        $results      = [];
        $successCount = 0;
        $warningCount = 0;
        $failedCount  = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2: row 1 is the CSV header

            $result = $createAction->execute($row);

            $results[] = [
                'row'     => $rowNumber,
                'status'  => $result['status'],
                'message' => $result['message'],
                'data'    => $result['data'] ? [
                    'member_id' => $result['data']->member_id,
                    'email'     => $result['data']->email,
                ] : null,
                'errors'  => $result['errors'] ?: null,
            ];

            match ($result['status']) {
                'success' => $successCount++,
                'warning' => $warningCount++,
                default   => $failedCount++,
            };

            // Flush progress to DB every 50 rows so the status endpoint
            // can show live progress on large uploads without hammering the DB
            if (($index + 1) % 50 === 0) {
                $batch->update([
                    'processed_rows' => $index + 1,
                    'success_count'  => $successCount,
                    'warning_count'  => $warningCount,
                    'failed_count'   => $failedCount,
                ]);
            }
        }

        // 6. Write final state
        $batch->update([
            'status'         => 'completed',
            'processed_rows' => $rows->count(),
            'success_count'  => $successCount,
            'warning_count'  => $warningCount,
            'failed_count'   => $failedCount,
            'results'        => $results,
            'completed_at'   => now(),
        ]);

        // 7. Clean up the stored CSV — no longer needed
        Storage::delete($batch->file_path);

        Log::info('BulkMemberDataProcessiong: job completed', [
            'batch_id' => $this->batchId,
            'total'    => $rows->count(),
            'success'  => $successCount,
            'warnings' => $warningCount,
            'failed'   => $failedCount,
        ]);
    }

    // ── Failure hooks ──────────────────────────────────────────

    /**
     * Called by Laravel when all retry attempts are exhausted.
     * Marks the batch as failed so the client gets a clear status.
     */
    public function failed(Throwable $exception): void
    {
        Log::error('BulkMemberDataProcessiong: job failed after retries', [
            'batch_id'  => $this->batchId,
            'exception' => $exception->getMessage(),
        ]);

        $batch = BulkUploadBatch::where('batch_id', $this->batchId)->first();

        if ($batch) {
            $this->markFailed($batch, $exception->getMessage());
        }
    }

    // ── Private ────────────────────────────────────────────────

    private function markFailed(BulkUploadBatch $batch, string $message): void
    {
        $batch->update([
            'status'        => 'failed',
            'error_message' => $message,
            'completed_at'  => now(),
        ]);
    }
}
