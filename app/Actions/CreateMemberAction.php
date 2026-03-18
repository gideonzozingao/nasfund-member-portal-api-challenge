<?php

namespace App\Actions;

use App\Models\Member;
use App\Repositories\MemberRepository;
use App\Validators\MemberValidator;
use Illuminate\Support\Facades\Log;

/**
 * Single-responsibility action: validate → duplicate-check → persist → log.
 *
 * Returns a result array instead of throwing, so callers (Service, CSV loop)
 * can decide how to handle each outcome.
 *
 * Result shape:
 * [
 *   'status'  => 'success' | 'error' | 'warning',
 *   'message' => string,
 *   'data'    => Member|null,
 *   'errors'  => array,
 * ]
 */
class CreateMemberAction
{
    public function __construct(private MemberRepository $repo) {}

    public function execute(array $data): array
    {
        // 1. Validate
        $check = MemberValidator::check($data);
        if (! $check['valid']) {
            return [
                'status'  => 'error',
                'message' => 'Validation failed.',
                'data'    => null,
                'errors'  => $check['errors'],
            ];
        }

        // 2. Hard duplicate: email
        if ($this->repo->findByEmail($data['email'])) {
            return $this->duplicate('A member with this email already exists.');
        }

        // 3. Hard duplicate: phone
        if ($this->repo->findByPhone($data['phone'])) {
            return $this->duplicate('A member with this phone already exists.');
        }

        // 4. Soft duplicate: name + DOB
        $softDuplicate = $this->repo->findByNameAndDob(
            $data['firstName'],
            $data['lastName'],
            $data['dateOfBirth']
        );

        // 5. Create member — member_id is auto-assigned by MemberObserver::creating()
        $member = $this->repo->create([
            'first_name'        => $data['firstName'],
            'last_name'         => $data['lastName'],
            'date_of_birth'     => $data['dateOfBirth'],
            'gender'            => $data['gender'],
            'email'             => $data['email'],
            'phone'             => $data['phone'],
            'employer_name'     => $data['employerName'],
            'employment_status' => $data['employmentStatus'],
            'tax_file_number'   => $data['taxFileNumber'],
        ]);

        Log::info('Member created', ['member_id' => $member->member_id, 'email' => $member->email]);

        if ($softDuplicate) {
            return [
                'status'  => 'warning',
                'message' => 'Member created but a potential duplicate was detected (same name and date of birth).',
                'data'    => $member,
                'errors'  => [],
            ];
        }

        return [
            'status'  => 'success',
            'message' => 'Member created successfully.',
            'data'    => $member,
            'errors'  => [],
        ];
    }

    private function duplicate(string $message): array
    {
        return [
            'status'  => 'error',
            'message' => $message,
            'data'    => null,
            'errors'  => ['duplicate' => [$message]],
        ];
    }
}
