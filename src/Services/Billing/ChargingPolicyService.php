<?php

namespace App\Services\Billing;

use App\Framework\Database\Database;
use App\Framework\Support\Logger;
use App\Models\Member;
use App\Repositories\Members\MemberRepository;
use Exception;

/**
 * Owns the charging_disabled policy for a member.
 *
 * All charge paths (subscription renewal, retry, manual charge) MUST call
 * assertChargingAllowed() before proceeding. The check is cheap (single column
 * read) and provides a single enforcement point so UI bypasses are impossible.
 */
class ChargingPolicyService
{
    private Database $database;

    public function __construct(
        private readonly MemberRepository $memberRepository,
        ?Database $database = null,
    ) {
        $this->database = $database ?? Database::getInstance();
    }

    /**
     * Throws if charging is disabled for this member.
     * Call this at the start of any billing action.
     */
    public function assertChargingAllowed(int $memberId): void
    {
        $member = $this->memberRepository->find($memberId);

        if (!$member) {
            throw new Exception('Member not found');
        }

        if ($member->charging_disabled) {
            throw new Exception(
                'Charging is disabled for this member' .
                ($member->charging_disabled_reason ? ': ' . $member->charging_disabled_reason : '.')
            );
        }
    }

    public function isChargingDisabled(int $memberId): bool
    {
        $member = $this->memberRepository->find($memberId);
        return $member ? (bool) $member->charging_disabled : false;
    }

    public function disableCharging(int $memberId, int $disabledByUserId, ?string $reason = null): Member
    {
        return $this->database->transaction(function () use ($memberId, $disabledByUserId, $reason) {
            $member = $this->memberRepository->find($memberId);

            if (!$member) {
                throw new Exception('Member not found');
            }

            $this->memberRepository->update($memberId, [
                'charging_disabled'        => true,
                'charging_disabled_reason' => $reason,
                'charging_disabled_at'     => date('Y-m-d H:i:s'),
                'charging_disabled_by'     => $disabledByUserId,
            ]);

            Logger::info('Charging disabled for member', [
                'member_id'   => $memberId,
                'disabled_by' => $disabledByUserId,
                'reason'      => $reason,
            ]);

            return $this->memberRepository->find($memberId);
        });
    }

    public function enableCharging(int $memberId, int $enabledByUserId): Member
    {
        return $this->database->transaction(function () use ($memberId, $enabledByUserId) {
            $member = $this->memberRepository->find($memberId);

            if (!$member) {
                throw new Exception('Member not found');
            }

            $this->memberRepository->update($memberId, [
                'charging_disabled'        => false,
                'charging_disabled_reason' => null,
                'charging_disabled_at'     => null,
                'charging_disabled_by'     => null,
            ]);

            Logger::info('Charging re-enabled for member', [
                'member_id'  => $memberId,
                'enabled_by' => $enabledByUserId,
            ]);

            return $this->memberRepository->find($memberId);
        });
    }
}