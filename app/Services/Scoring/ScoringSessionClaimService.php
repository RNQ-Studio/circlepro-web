<?php

namespace App\Services\Scoring;

use App\Models\ScoringSession;
use App\Models\ScoringSessionClaim;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use App\Services\PushNotificationService;
use App\Support\Enums\ClaimStatus;
use App\Support\Enums\ParticipationStatus;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Klaim & approval host — Sprint 13, Phase 2 (loop akuisisi).
 *
 * The moment a name becomes a user: a signed-in archer claims a guest slot
 * ("Ini Saya"); the host approves from a context-rich inbox; on approval
 * ownership transfers transactionally — including the slot's REAL
 * distance/face/butt — so the sweetest moment never turns into a "PB 50m
 * palsu" (§3.2, K17 echo). This service owns the claim lifecycle; binder
 * integrity (§1) means a guest slot is just a scoring_sessions row, so a
 * transfer is an ownership flip + a recompute, not a data migration.
 */
class ScoringSessionClaimService
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly PushNotificationService $push,
    ) {}

    /**
     * 13.1 — A signed-in archer claims a guest slot. Only a guest row of THIS
     * group is claimable (anti-abuse 13.7). The unique (slot, claimant) index
     * makes a double-claim impossible; rather than trip it, a previously
     * cancelled/rejected claim is revived to pending so the archer can try
     * again, while a pending duplicate or an already-won slot is rejected.
     */
    public function submitClaim(
        ScoringSessionGroup $group,
        ScoringSession $session,
        User $claimant,
        ?string $message,
    ): ScoringSessionClaim {
        abort_unless($session->scoring_session_group_id === $group->id, 404, 'Resource not found.');
        abort_unless($session->isGuest(), 422, 'Hanya slot tamu yang bisa diklaim.');

        // Capture the slot's display name before it is cleared on approval — the
        // host inbox & notification read more naturally with "slot Budi".
        $slotName = $session->guest_name;

        $claim = DB::transaction(function () use ($group, $session, $claimant, $message): ScoringSessionClaim {
            $existing = ScoringSessionClaim::query()
                ->where('scoring_session_id', $session->id)
                ->where('claimant_user_id', $claimant->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                abort_if($existing->status === ClaimStatus::Pending, 422, 'Kamu sudah mengajukan klaim atas slot ini.');
                abort_if($existing->status === ClaimStatus::Approved, 422, 'Slot ini sudah menjadi milikmu.');

                $existing->update([
                    'status' => ClaimStatus::Pending,
                    'message' => $message,
                    'resolved_by_user_id' => null,
                    'resolved_at' => null,
                ]);

                return $existing;
            }

            return ScoringSessionClaim::query()->create([
                'scoring_session_id' => $session->id,
                'scoring_session_group_id' => $group->id,
                'claimant_user_id' => $claimant->id,
                'status' => ClaimStatus::Pending,
                'message' => $message,
            ]);
        });

        $this->notifyHostOfNewClaim($group, $claim, $claimant, $slotName);

        return $claim;
    }

    /**
     * 14.1 — The guest slots of a group, for a code-holder to find and claim
     * theirs ("Ini Saya"). Completes the claim loop on the mobile side: the
     * roster/leaderboard is gated to host+participants (§4 matrix), and lookup
     * hides the roster, so a fresh guest who taps the shared result card had no
     * way to discover their slot without first minting a junk self-row. This
     * read is open to any authenticated code-holder (the group id is an
     * unguessable ULID resolved via lookup-by-code, and a guest's name + score
     * already ride on the publicly shared card) and returns only guest rows,
     * each annotated with THIS user's own claim status so the app can paint the
     * "Menunggu persetujuan host" badge without a second call.
     *
     * @return array<int, array<string, mixed>>
     */
    public function claimableSlots(ScoringSessionGroup $group, User $user): array
    {
        /** @var Collection<int, ScoringSession> $slots */
        $slots = $group->participants()
            ->whereNull('user_id')
            ->orderByDesc('total_score')
            ->orderBy('started_at')
            ->get();

        // The caller's own claims over this group, keyed by slot, so each row
        // knows whether *I* already claimed it (pending) — one query, no N+1.
        $myClaims = ScoringSessionClaim::query()
            ->where('scoring_session_group_id', $group->id)
            ->where('claimant_user_id', $user->id)
            ->get()
            ->keyBy('scoring_session_id');

        return $slots->map(function (ScoringSession $slot) use ($myClaims): array {
            /** @var ScoringSessionClaim|null $mine */
            $mine = $myClaims->get($slot->id);

            return [
                'session_id' => $slot->id,
                'display_name' => $slot->guest_name,
                'started_at' => $slot->started_at->toIso8601String(),
                'distance_category' => $slot->distance_category?->value,
                'distance_m' => $slot->distance_m,
                'target_face_cm' => $slot->target_face_cm,
                'status' => $slot->status?->value,
                'total_score' => $slot->total_score,
                'arrows_shot' => $slot->arrows_shot,
                'x_count' => $slot->x_count,
                'ten_count' => $slot->ten_count,
                // Annotate with my own claim so the app paints the badge / hides
                // the "Ini Saya" CTA for a slot I have already claimed.
                'my_claim_status' => $mine?->status->value,
                'my_claim_id' => $mine?->id,
            ];
        })->all();
    }

    /**
     * 13.2 — Host inbox: claims to review for a group, newest first, optionally
     * filtered by status. The slot + claimant are eager-loaded so the host can
     * decide from memory (the slot's score, when it was shot, the name) rather
     * than a guess.
     *
     * @return Collection<int, ScoringSessionClaim>
     */
    public function hostInbox(ScoringSessionGroup $group, ?ClaimStatus $status = null): Collection
    {
        return ScoringSessionClaim::query()
            ->where('scoring_session_group_id', $group->id)
            ->when($status !== null, fn ($q) => $q->where('status', $status))
            ->with(['claimant:id,name', 'session'])
            ->latest()
            ->get();
    }

    /**
     * 13.3 / 13.4 / 13.5 — Approve a claim. Atomic: ownership of the guest slot
     * transfers to the claimant, then aggregates + personal-best + gamification
     * are (re)computed for the new owner, and every other pending claim over the
     * same slot is auto-rejected. The slot's OWN distance/face/butt stay on the
     * row — they are the truth the archer shot at, so the PB that is born is
     * honest (15m stays 15m), never a "PB 50m palsu".
     */
    public function approveClaim(ScoringSessionClaim $claim, User $host): ScoringSessionClaim
    {
        abort_unless($claim->status === ClaimStatus::Pending, 422, 'Klaim ini sudah diproses.');

        /** @var array{0: ScoringSessionClaim, 1: Collection<int, ScoringSessionClaim>} $result */
        $result = DB::transaction(function () use ($claim, $host): array {
            /** @var ScoringSession $session */
            $session = $claim->session()->lockForUpdate()->firstOrFail();

            // Another claimant may have won this slot first; only a still-guest
            // slot can be transferred.
            abort_unless($session->isGuest(), 422, 'Slot ini sudah dimiliki pemanah lain.');

            // --- Transactional ownership transfer (13.3) ---
            // distance_m / target_face_cm / target_butt / distance_category live
            // on the row already and are deliberately left untouched so they
            // "ikut tertransfer" into the new owner's history with the right
            // distance. We only flip identity.
            $session->user_id = $claim->claimant_user_id;
            $session->guest_name = null;
            $session->participation_status = ParticipationStatus::Self;
            $session->save();

            // --- Recompute for the new owner (13.4) ---
            // The cached totals were already computed when the guest was scored;
            // what was skipped (because user_id was NULL, §3.2) is PB +
            // gamification. We recompute defensively, then light up both.
            $this->scoring->recomputeAggregates($session);

            if ($session->status === ScoringSessionStatus::Completed
                && $session->bow_class !== null
                && $session->distance_category !== null) {
                $this->scoring->evaluatePersonalBest($session);
            }
            $session->save();

            if ($session->status === ScoringSessionStatus::Completed) {
                $this->scoring->awardSessionGamification($session);
            }

            $claim->update([
                'status' => ClaimStatus::Approved,
                'resolved_by_user_id' => $host->id,
                'resolved_at' => now(),
            ]);

            // --- Auto-reject the losing claims over the same slot (13.5) ---
            $others = ScoringSessionClaim::query()
                ->where('scoring_session_id', $session->id)
                ->whereKeyNot($claim->id)
                ->where('status', ClaimStatus::Pending)
                ->with(['claimant:id,name', 'group'])
                ->get();

            foreach ($others as $other) {
                $other->update([
                    'status' => ClaimStatus::Rejected,
                    'resolved_by_user_id' => $host->id,
                    'resolved_at' => now(),
                ]);
            }

            return [$claim->fresh(['claimant', 'session', 'group']), $others];
        });

        [$approved, $autoRejected] = $result;

        // Notify after commit so a rolled-back transfer never pushes a phantom.
        $this->notifyClaimant($approved, ClaimStatus::Approved);
        foreach ($autoRejected as $other) {
            $this->notifyClaimant($other, ClaimStatus::Rejected);
        }

        return $approved;
    }

    /**
     * 13.5 — Host rejects a pending claim by hand. No ownership changes.
     */
    public function rejectClaim(ScoringSessionClaim $claim, User $host): ScoringSessionClaim
    {
        abort_unless($claim->status === ClaimStatus::Pending, 422, 'Klaim ini sudah diproses.');

        $claim->update([
            'status' => ClaimStatus::Rejected,
            'resolved_by_user_id' => $host->id,
            'resolved_at' => now(),
        ]);

        $this->notifyClaimant($claim->fresh(['claimant', 'group']), ClaimStatus::Rejected);

        return $claim;
    }

    /**
     * 13.5 — The claimant withdraws their own pending claim. Kept as a Cancelled
     * row (not deleted) so the audit trail survives; a later submitClaim revives
     * it instead of tripping the unique index.
     */
    public function cancelClaim(ScoringSessionClaim $claim): ScoringSessionClaim
    {
        abort_unless(
            $claim->status === ClaimStatus::Pending,
            422,
            'Hanya klaim yang masih menunggu yang bisa dibatalkan.',
        );

        $claim->update(['status' => ClaimStatus::Cancelled]);

        return $claim;
    }

    /**
     * 13.6 — Tell the host a new claim landed in their inbox.
     */
    private function notifyHostOfNewClaim(
        ScoringSessionGroup $group,
        ScoringSessionClaim $claim,
        User $claimant,
        ?string $slotName,
    ): void {
        $host = $group->host;
        if ($host === null) {
            return;
        }

        $slot = $slotName !== null ? "slot \"{$slotName}\"" : 'sebuah slot tamu';

        $this->push->send(
            $host,
            'Ada klaim slot baru',
            "{$claimant->name} mengklaim {$slot} di {$this->groupLabel($group)}.",
            $this->deepLinkPayload($group, $claim, 'group_claim_submitted'),
            'group_claim_submitted',
        );
    }

    /**
     * 13.6 — Tell the claimant whether their claim was approved or rejected.
     */
    private function notifyClaimant(ScoringSessionClaim $claim, ClaimStatus $outcome): void
    {
        $claimant = $claim->claimant;
        if ($claimant === null) {
            return;
        }

        $group = $claim->group;
        $label = $group !== null ? $this->groupLabel($group) : 'Latihan Bersama';

        [$title, $body, $type] = $outcome === ClaimStatus::Approved
            ? ['Klaim disetujui', "Skor di {$label} kini milikmu — PB & statistikmu sudah diperbarui.", 'group_claim_approved']
            : ['Klaim ditolak', "Klaim atas slot di {$label} belum disetujui.", 'group_claim_rejected'];

        $this->push->send(
            $claimant,
            $title,
            $body,
            $group !== null ? $this->deepLinkPayload($group, $claim, $type) : ['type' => $type, 'claim_id' => $claim->id],
            $type,
        );
    }

    /**
     * Deep-link payload (§5 Fase 2) the mobile app routes on (Sprint 14): the
     * group + slot + claim ids plus a scheme link to open the group directly.
     *
     * @return array<string, mixed>
     */
    private function deepLinkPayload(ScoringSessionGroup $group, ScoringSessionClaim $claim, string $type): array
    {
        return [
            'type' => $type,
            'group_id' => $group->id,
            'session_id' => $claim->scoring_session_id,
            'claim_id' => $claim->id,
            'join_code' => $group->join_code,
            'link' => config('deeplink.scheme').'://groups/'.$group->id,
        ];
    }

    private function groupLabel(ScoringSessionGroup $group): string
    {
        return $group->title ?? 'Latihan Bersama';
    }
}
