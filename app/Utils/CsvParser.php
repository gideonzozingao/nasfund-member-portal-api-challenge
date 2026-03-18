<?php

namespace App\Utils;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class CsvParser
{
    /**
     * Parse a CSV UploadedFile into a Collection of associative arrays.
     * The first row is treated as the header.
     *
     * Expected headers (case-insensitive):
     *   firstName, lastName, dateOfBirth, gender, email,
     *   phone, employerName, employmentStatus, taxFileNumber
     */
    /**
     * Parse from a stored file path (used by the background job).
     */
    public function parseFromPath(string $path): Collection
    {
        // Reuse the same logic via a temporary UploadedFile wrapper
        $uploaded = new UploadedFile($path, basename($path), null, null, true);
        return $this->parse($uploaded);
    }

    public function parse(UploadedFile $file): Collection
    {
        $rows      = [];
        $handle    = fopen($file->getRealPath(), 'r');
        $headers   = null;

        while (($line = fgetcsv($handle, 1000, ',')) !== false) {
            if ($headers === null) {
                // Normalise headers: trim whitespace
                $headers = array_map('trim', $line);
                continue;
            }

            if (count($line) !== count($headers)) {
                continue; // skip malformed rows silently
            }

            $row = array_combine($headers, array_map('trim', $line));

            // Normalise common header variations
            $row = $this->normaliseKeys($row);

            $rows[] = $row;
        }

        fclose($handle);

        return collect($rows);
    }

    /**
     * Map common CSV header variations to the canonical camelCase keys.
     */
    private function normaliseKeys(array $row): array
    {
        $map = [
            'first_name'        => 'firstName',
            'last_name'         => 'lastName',
            'date_of_birth'     => 'dateOfBirth',
            'dob'               => 'dateOfBirth',
            'employer_name'     => 'employerName',
            'employment_status' => 'employmentStatus',
            'tax_file_number'   => 'taxFileNumber',
            'tfn'               => 'taxFileNumber',
        ];

        $normalised = [];
        foreach ($row as $key => $value) {
            $canonical           = $map[strtolower($key)] ?? $key;
            $normalised[$canonical] = $value;
        }

        return $normalised;
    }
}
