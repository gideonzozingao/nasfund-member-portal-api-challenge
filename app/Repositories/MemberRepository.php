<?php

namespace App\Repositories;

use App\Models\Member;
use Illuminate\Support\Collection;

class MemberRepository
{
    // ── Reads ──────────────────────────────────────────────────

    public function findByEmail(string $email): ?Member
    {
        return Member::where('email', $email)->first();
    }

    public function findByPhone(string $phone): ?Member
    {
        return Member::where('phone', $phone)->first();
    }

    /**
     * Soft-duplicate check: same full name + DOB (different email/phone).
     */
    public function findByNameAndDob(string $firstName, string $lastName, string $dob): ?Member
    {
        return Member::where('first_name', $firstName)
            ->where('last_name', $lastName)
            ->whereDate('date_of_birth', $dob)
            ->first();
    }

    // ── Writes ─────────────────────────────────────────────────

    public function create(array $data): Member
    {
        return Member::create($data);
    }

    /**
     * Batch insert for bulk-upload (skips model events — use consciously).
     */
    public function insertMany(array $rows): void
    {
        Member::insert($rows);
    }

    // ── Utilities ──────────────────────────────────────────────

    /**
     * Generate the next sequential member ID (M000000001 format).
     */
    public function nextMemberId(): string
    {
        $last = Member::withTrashed()->max('id') ?? 0;
        return 'M' . str_pad($last + 1, 9, '0', STR_PAD_LEFT);
    }
}
