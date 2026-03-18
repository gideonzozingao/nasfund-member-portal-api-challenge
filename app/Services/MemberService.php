<?php

namespace App\Services;

use App\Actions\CreateMemberAction;
use App\DTOs\CreateMemberResponseDTO;
use App\Models\Member;

class MemberService
{
    public function __construct(private CreateMemberAction $createAction) {}

    /**
     * Create a single member via the API.
     */
    public function create(array $data): CreateMemberResponseDTO
    {
        return CreateMemberResponseDTO::fromActionResult(
            $this->createAction->execute($data)
        );
    }

    /**
     * Retrieve a member by their member_id.
     */
    public function findById(string $memberId): ?Member
    {
        return Member::where('member_id', $memberId)->first();
    }
}
