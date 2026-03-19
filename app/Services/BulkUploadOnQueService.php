<?php

namespace App\Services;

use App\Jobs\BulkMemberDataProcessing;
use App\Models\BulkUploadBatch;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class BulkUploadOnQueService
{
    /**
     * Accept a CSV upload, persist it to disk, create a tracking record,
     * and dispatch the background job.
     *
     * Returns the BulkUploadBatch so the controller can give the client
     * a batch_id to poll for progress.
     *
     * Heavy lifting (row parsing + member creation) is entirely inside
     * the BulkMemberDataProcessiong job — this method is intentionally thin.
     */
    public function dispatch(UploadedFile $file): BulkUploadBatch
    {
        // 1. Persist the CSV to the 'local' disk under bulk-uploads/
        //    We store it before dispatching so the job can always find it,
        //    even if it is delayed in the queue for several minutes.
        $storedPath = $file->store('bulk-uploads', 'local');

        Log::info('BulkUploadService: CSV stored', [
            'path' => $storedPath,
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        // 2. Create the batch tracking record (status: pending)
        $batch = BulkUploadBatch::create([
            'file_path' => $storedPath,
            'original_filename' => $file->getClientOriginalName(),
            'status' => 'pending',
        ]);

        // 3. Dispatch the job — passes only the batch_id (small payload)
        BulkMemberDataProcessing::dispatch($batch->batch_id)
            ->onQueue('bulk-uploads');   // dedicated queue keeps bulk work
        // from blocking single-member creates

        Log::info('BulkUploadService: job dispatched', [
            'batch_id' => $batch->batch_id,
        ]);

        return $batch;
    }

    /**
     * Retrieve a batch by its public UUID.
     * Returns null if not found so the controller can 404 cleanly.
     */
    public function findBatch(string $batchId): ?BulkUploadBatch
    {
        return BulkUploadBatch::where('batch_id', $batchId)->first();
    }
}
