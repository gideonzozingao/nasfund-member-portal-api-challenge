<?php

namespace App\Utils;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

class CsvParser
{
    /**
     * Canonical camelCase keys this parser guarantees in every output row.
     *
     * The lookup key is the header reduced to lowercase with all
     * whitespace, underscores, and hyphens removed, so every common
     * variation (FirstName / first_name / FIRST-NAME / "First Name")
     * resolves to the same slug and maps to the same canonical key.
     */
    private const HEADER_MAP = [
        'firstname' => 'firstName',
        'lastname' => 'lastName',
        'dateofbirth' => 'dateOfBirth',
        'dob' => 'dateOfBirth',
        'gender' => 'gender',
        'email' => 'email',
        'phone' => 'phone',
        'employername' => 'employerName',
        'employmentstatus' => 'employmentStatus',
        'taxfilenumber' => 'taxFileNumber',
        'tfn' => 'taxFileNumber',
    ];

    /**
     * Parse from a stored file path (used by the background job).
     */
    public function parseFromPath(string $path): Collection
    {
        $uploaded = new UploadedFile($path, basename($path), null, null, true);

        return $this->parse($uploaded);
    }

    public function parse(UploadedFile $file): Collection
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        $headers = null;

        while (($line = fgetcsv($handle, 1000, ',')) !== false) {
            if ($headers === null) {
                // Normalise every header once, up front.
                $headers = array_map([$this, 'normaliseHeader'], $line);

                continue;
            }

            if (count($line) !== count($headers)) {
                continue; // skip malformed rows silently
            }

            $rows[] = array_combine($headers, array_map('trim', $line));
        }

        fclose($handle);

        return collect($rows);
    }

    /**
     * Reduce a raw header string to its canonical camelCase key.
     *
     * Resolution order:
     *   1. Trim surrounding whitespace.
     *   2. Build a lowercase slug by stripping spaces, underscores and hyphens.
     *   3. Look the slug up in HEADER_MAP.
     *   4. If not found, fall back to a generic camelCase conversion so
     *      unexpected columns survive rather than getting silently dropped.
     */
    private function normaliseHeader(string $header): string
    {
        $trimmed = trim($header);
        $slug = strtolower(preg_replace('/[\s_\-]+/', '', $trimmed));

        if (isset(self::HEADER_MAP[$slug])) {
            return self::HEADER_MAP[$slug];
        }

        // Generic fallback: "Some Column" / "some_column" → "someColumn"
        $words = preg_split('/[\s_\-]+/', $trimmed);
        $camel = strtolower(array_shift($words))
            .implode('', array_map('ucfirst', array_map('strtolower', $words)));

        return $camel;
    }
}
