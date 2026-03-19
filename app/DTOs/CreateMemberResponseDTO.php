<?php

namespace App\DTOs;

use App\Models\Member;

final class CreateMemberResponseDTO
{
    private function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $memberId,
        public readonly ?string $name,
        public readonly ?string $createdAt,
        public readonly array $errors,
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
            name: $member ? "{$member->first_name} {$member->last_name}" : null,
            createdAt: $member?->created_at?->toIso8601String(),
            errors: $result['errors'] ?? [],
        );
    }

    // ── Serialisation ──────────────────────────────────────────

    /**
     * Serialises to the target bulk-result row shape:
     *
     * Success / warning:
     *   { status, memberId, name, message, createdAt }
     *
     * Error:
     *   { status, name, errors: ["flat string", ...] }
     */
    public function toArray(): array
    {
        if ($this->status === 'error') {
            return [
                'status' => $this->status,
                'name' => $this->name,
                'errors' => $this->flatErrors(),
            ];
        }

        return [
            'status' => $this->status,
            'memberId' => $this->memberId,
            'name' => $this->name,
            'message' => $this->message,
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
            default => 422,
        };
    }

    /**
     * Flatten the nested ['field' => ['msg1', 'msg2']] error map
     * into a simple ["msg1", "msg2"] array expected by the API contract.
     */
    private function flatErrors(): array
    {
        if (empty($this->errors)) {
            return [];
        }

        return array_values(
            array_merge(...array_values(
                array_map(fn (array $msgs) => array_values($msgs), $this->errors)
            ))
        );
    }
}
