<?php

namespace App\Observers;

use App\Models\Member;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemberObserver
{
    /**
     * Handle the Member "creating" event.
     *
     * Fires BEFORE the INSERT — so we can stamp member_id onto the model
     * before it ever touches the database. No manual ID passing required.
     *
     * Format: M + 9 zero-padded digits → M000000001
     *
     * We use a DB-level lock (lockForUpdate) to prevent race conditions
     * when two requests hit the endpoint at the same millisecond.
     */
    public function creating(Member $member): void
    {
        // Only auto-generate if one hasn't been set manually (e.g. in seeders)
        if (! empty($member->member_id)) {
            return;
        }

        $member->member_id = $this->generateMemberId();

        Log::info('MemberObserver: member_id assigned', [
            'member_id' => $member->member_id,
        ]);
    }

    /**
     * Handle the Member "created" event.
     *
     * Fires AFTER the INSERT — useful for side-effects like
     * sending a welcome notification or triggering a downstream event.
     */
    public function created(Member $member): void
    {
        Log::info('MemberObserver: new member persisted', [
            'member_id' => $member->member_id,
            'email' => $member->email,
        ]);

        // Future: dispatch(new SendWelcomeEmail($member));
        // Future: event(new MemberRegistered($member));
    }

    /**
     * Handle the Member "updating" event.
     *
     * Fires BEFORE an UPDATE — good place to guard immutable fields.
     * member_id must never change once assigned.
     */
    public function updating(Member $member): void
    {
        if ($member->isDirty('member_id')) {
            // Restore the original value and log the attempt
            $member->member_id = $member->getOriginal('member_id');

            Log::warning('MemberObserver: attempt to mutate member_id blocked', [
                'member_id' => $member->member_id,
            ]);
        }
    }

    /**
     * Handle the Member "updated" event.
     */
    public function updated(Member $member): void
    {
        Log::info('MemberObserver: member updated', [
            'member_id' => $member->member_id,
            'changed' => array_keys($member->getChanges()),
        ]);
    }

    /**
     * Handle the Member "deleted" event (soft delete).
     */
    public function deleted(Member $member): void
    {
        Log::info('MemberObserver: member soft-deleted', [
            'member_id' => $member->member_id,
        ]);
    }

    /**
     * Handle the Member "restored" event (soft delete reversed).
     */
    public function restored(Member $member): void
    {
        Log::info('MemberObserver: member restored', [
            'member_id' => $member->member_id,
        ]);
    }

    /**
     * Handle the Member "force deleted" event.
     */
    public function forceDeleted(Member $member): void
    {
        Log::warning('MemberObserver: member permanently deleted', [
            'member_id' => $member->member_id,
            'email' => $member->email,
        ]);
    }

    // ── Private ────────────────────────────────────────────────

    /**
     * Generate the next sequential member ID inside a transaction lock
     * to guarantee uniqueness under concurrent requests.
     *
     * Uses withTrashed() so IDs from soft-deleted records are never reused.
     */
    private function generateMemberId(): string
    {
        return DB::transaction(function () {
            // Lock the latest row so concurrent transactions queue up here
            $last = Member::withTrashed()
                ->lockForUpdate()
                ->orderByDesc('id')
                ->value('member_id'); // e.g. "M000000042" or null

            $next = $last
                ? (int) ltrim(substr($last, 1), '0') + 1  // strip "M" then cast
                : 1;

            return 'M'.str_pad($next, 9, '0', STR_PAD_LEFT);
        });
    }
}
