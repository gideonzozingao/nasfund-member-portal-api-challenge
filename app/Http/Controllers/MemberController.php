<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateMemberRequest;
use App\Services\BulkUploadService;
use App\Services\MemberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function __construct(
        private MemberService      $memberService,
        private BulkUploadService  $bulkUploadService,
    ) {}

    // ── POST /api/v1/members/create ────────────────────────────

    public function create(CreateMemberRequest $request): JsonResponse
    {
        $result = $this->memberService->create($request->validated());

        $httpStatus = match ($result['status']) {
            'success' => 201,
            'warning' => 201,
            default   => 422,
        };

        return response()
            ->json($this->envelope($result), $httpStatus);
        // return response()->json(['success' => 201,], 201);
    }

    // ── POST /api/v1/members/bulk-upload ───────────────────────
    //    Stores the file and dispatches the job. Returns 202 immediately.

    public function bulkUpload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $batch = $this->bulkUploadService
            ->dispatch($request->file('file'));

        return response()->json([
            'status'   => 'accepted',
            'message'  => 'Your file has been queued for processing.',
            'batch_id' => $batch->batch_id,
            'poll_url' => url("/api/v1/members/bulk-upload/{$batch->batch_id}/status"),
        ], 202);
    }

    // ── GET /api/v1/members/bulk-upload/{batchId}/status ───────
    //    Polled by the client to check progress and retrieve results.

    public function uploadStatus(string $batchId): JsonResponse
    {
        $batch = $this->bulkUploadService->findBatch($batchId);

        if (! $batch) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Batch not found.',
            ], 404);
        }

        $payload = [
            'status'   => $batch->status,
            'batch_id' => $batch->batch_id,
            'file'     => $batch->original_filename,
            'summary'  => $batch->summary,
            'timing'   => [
                'queued_at'    => $batch->created_at?->toIso8601String(),
                'started_at'   => $batch->started_at?->toIso8601String(),
                'completed_at' => $batch->completed_at?->toIso8601String(),
            ],
        ];

        // Only include per-row results once the job has finished
        if (in_array($batch->status, ['completed', 'failed'])) {
            $payload['results']       = $batch->results;
            $payload['error_message'] = $batch->error_message;
        }

        return response()->json($payload, 200);
    }

    // ── GET /api/v1/members/{memberId} ─────────────────────────

    public function show(string $memberId): JsonResponse
    {
        $member = $this->memberService->findById($memberId);

        if (! $member) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Member not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data'   => $member,
        ]);
    }

    // ── GET /api/v1/health ─────────────────────────────────────

    public function health(): JsonResponse
    {
        return response()->json([
            'status'    => 'ok',
            'timestamp' => now()->toIso8601String(),
            'version'   => config('app.version', '1.0.0'),
        ]);
    }

    // ── Helpers ────────────────────────────────────────────────

    private function envelope(array $result): array
    {
        return [
            'status'  => $result['status'],
            'message' => $result['message'],
            'data'    => $result['data'],
            'errors'  => $result['errors'] ?: null,
        ];
    }
}
