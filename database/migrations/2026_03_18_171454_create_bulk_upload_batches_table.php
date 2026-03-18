<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bulk_upload_batches', function (Blueprint $table) {
            $table->id();
            $table->uuid('batch_id')->unique()->index();   // returned to the client
            $table->string('file_path');                   // stored CSV location
            $table->string('original_filename', 255);
            $table->enum('status', [
                'pending',     // job queued, not started
                'processing',  // job is running
                'completed',   // all rows processed (some may have failed)
                'failed',      // job itself crashed (not row-level failures)
            ])->default('pending')->index();

            // Running counters updated by the job as it processes rows
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('warning_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            // Full per-row results JSON — stored once job completes
            $table->json('results')->nullable();

            // Job-level error (only set when status = 'failed')
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bulk_upload_batches');
    }
};
