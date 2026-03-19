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
        private MemberService $memberService,
        private BulkUploadService $bulkUploadService,
    ) {}

    // ── POST /api/v1/members/create ────────────────────────────

    public function create(CreateMemberRequest $request): JsonResponse
    {
        $dto = $this->memberService->create($request->validated());

        return response()->json($dto->toArray(), $dto->httpStatus());
    }

    // ── POST /api/v1/members/bulk-upload ───────────────────────

    public function bulkUpload(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
        ]);

        $summary = $this->bulkUploadService->process($request->file('file'));

        $status = match (true) {
            $summary['errorCount'] === $summary['totalRecords'] => 'error',
            $summary['errorCount'] > 0 => 'partial',
            default => 'success',
        };

        return response()->json([
            'status' => $status,
            'summary' => [
                'totalRecords' => $summary['totalRecords'],
                'successCount' => $summary['successCount'],
                'errorCount' => $summary['errorCount'],
            ],
            'results' => $summary['results'],
        ], 200);
    }

    // ── GET /api/v1/members/{memberId} ─────────────────────────

    public function show(string $memberId): JsonResponse
    {
        $member = $this->memberService->findById($memberId);

        if (! $member) {
            return response()->json([
                'status' => 'error',
                'message' => 'Member not found.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $member,
        ]);
    }

    // ── GET /api/v1/health ─────────────────────────────────────

    public function health(): JsonResponse
    {
        return response()->json([
            'status' => 'ok',
            'timestamp' => now()->toIso8601String(),
            'version' => config('app.version', '1.0.0'),
        ]);
    }
}
