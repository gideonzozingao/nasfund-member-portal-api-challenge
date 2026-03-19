<?php

namespace App\Services;

use App\Actions\CreateMemberAction;
use App\DTOs\CreateMemberResponseDTO;
use App\Utils\CsvParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

class BulkUploadService
{
    public function __construct(
        private CsvParser $parser,
        private CreateMemberAction $createAction,
    ) {}

    /**
     * Parse the CSV and create each member directly — no queue, no job.
     * Processes row-by-row so partial success is always possible.
     *
     * Returns:
     * [
     *   'totalRecords' => int,
     *   'successCount' => int,
     *   'errorCount'   => int,
     *   'results'      => [ ['row' => int, ...DTO fields], ... ]
     * ]
     */
    public function process(UploadedFile $file): array
    {
        Log::info('BulkUploadService: processing started', [
            'filename' => $file->getClientOriginalName(),
            'size' => $file->getSize(),
        ]);

        $rows = $this->parser->parse($file);
        $results = [];
        $successCount = 0;
        $errorCount = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // +2: row 1 is the CSV header

            $dto = CreateMemberResponseDTO::fromActionResult(
                $this->createAction->execute($row)
            );

            $results[] = array_merge(['row' => $rowNumber], $dto->toArray());

            $dto->isError() ? $errorCount++ : $successCount++;
        }

        Log::info('BulkUploadService: processing complete', [
            'filename' => $file->getClientOriginalName(),
            'totalRecords' => $rows->count(),
            'successCount' => $successCount,
            'errorCount' => $errorCount,
        ]);

        return [
            'totalRecords' => $rows->count(),
            'successCount' => $successCount,
            'errorCount' => $errorCount,
            'results' => $results,
        ];
    }
}
