<?php

namespace App\DTOs;

use App\Models\Member;

final class CreateMemberResponseDTO
{
    private function __construct(
        public readonly string  $status,
        public readonly string  $message,
        public readonly ?string $memberId,
        public readonly ?string $createdAt,
        public readonly array   $errors,
    ) {}

    // ── Named constructors ─────────────────────────────────────

    /**
     * Build from the raw result array returned by CreateMemberAction.
     */
    public static function fromActionResult(array $result): self
    {
        /** @var Member|null $member */
        $member = $result['data'] ?? null;

        return new self(
            status: $result['status'],
            message: $result['message'],
            memberId: $member?->member_id,
            createdAt: $member?->created_at?->toIso8601String(),
            errors: $result['errors'] ?? [],
        );
    }

    // ── Serialisation ──────────────────────────────────────────

    /**
     * JSON-ready array. Error responses omit the member fields;
     * success / warning responses omit the errors block.
     */
    public function toArray(): array
    {
        if ($this->status === 'error') {
            return [
                'status'  => $this->status,
                'message' => $this->message,
                'errors'  => $this->errors ?: null,
            ];
        }

        return [
            'status'    => $this->status,
            'memberId'  => $this->memberId,
            'message'   => $this->message,
            'createdAt' => $this->createdAt,
        ];
    }

    // ── Convenience helpers ────────────────────────────────────

    public function isError(): bool
    {
        return $this->status === 'error';
    }

    public function httpStatus(): int
    {
        return match ($this->status) {
            'success', 'warning' => 201,
            default              => 422,
        };
    }
}
