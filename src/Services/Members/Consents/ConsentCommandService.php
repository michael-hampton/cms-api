<?php

namespace App\Services\Members\Consents;

use App\DTO\Consents\ConsentActionContext;
use App\Enums\ConsentAction;
use App\Enums\ConsentWithdrawalStatus;
use App\Enums\ConsentWithdrawalType;
use App\Exceptions\Consents\ConsentWithdrawalInvalidStateException;
use App\Exceptions\Consents\RequiredConsentCannotBeRevokedException;
use App\Framework\Database\Database;
use App\Models\ConsentWithdrawalRequest;
use App\Models\Member;
use App\Models\MemberConsent;
use App\Repositories\Members\Consents\ConsentTypeRepository;
use App\Repositories\Members\Consents\MemberConsentRepository;

class ConsentCommandService
{
    public function __construct(
        private readonly Database                $database,
        private readonly ConsentTypeRepository   $consentTypeRepository,
        private readonly MemberConsentRepository $memberConsentRepository,
        private readonly ConsentAuditService     $auditService
    )
    {
    }

    public function grantConsent(
        Member               $member,
        string               $consentCode,
        ConsentActionContext $context
    ): MemberConsent
    {
        $consentType = $this->consentTypeRepository->findActiveByCode($consentCode);

        return $this->database->transaction(function () use ($member, $consentType, $context) {
            $consent = $this->memberConsentRepository->findByMemberAndType(
                $member->id,
                $consentType->id
            );

            $previousState = $consent ? $consent->is_granted : null;

            if (!$consent) {
                $consent = $this->memberConsentRepository->createNew([
                    'member_id' => $member->id,
                    'consent_type_id' => $consentType->id,
                ]);
            }

            $consent->is_granted = true;
            $consent->channel = $context->source;
            $consent->granted_at = now();
            $consent->revoked_at = null;
            $consent->site_id = $context->siteId ?? \App\Framework\Support\SiteContext::getId();
            $consent->ip_address = $context->ipAddress;
            $consent->user_agent = $context->userAgent;

            if ($consentType->retention_days) {
                $expiryDate = now_datetime()->modify("+{$consentType->retention_days} days");
                $consent->expires_at = $expiryDate->format('Y-m-d H:i:s');
            }

            $this->memberConsentRepository->save($consent);

            $this->auditService->log(
                $member,
                $consentType,
                ConsentAction::GRANTED,
                $previousState,
                true,
                $context
            );

            return $consent;
        });
    }

    public function revokeConsent(
        Member               $member,
        string               $consentCode,
        ConsentActionContext $context
    ): bool
    {
        $consentType = $this->consentTypeRepository->findActiveByCode($consentCode);

        if ($consentType->isRequired()) {
            throw new RequiredConsentCannotBeRevokedException($consentCode);
        }

        return $this->database->transaction(function () use ($member, $consentType, $context) {
            $consent = $this->memberConsentRepository->findByMemberAndType(
                $member->id,
                $consentType->id
            );

            if (!$consent) {
                return false;
            }

            $previousState = $consent->is_granted;

            $consent->is_granted = false;
            $consent->revoked_at = now();

            $this->memberConsentRepository->save($consent);

            $this->auditService->log(
                $member,
                $consentType,
                ConsentAction::REVOKED,
                $previousState,
                false,
                $context
            );

            return true;
        });
    }

    public function processExpiredConsents(): int
    {
        $expiredConsents = $this->memberConsentRepository->findExpired();
        $count = 0;

        foreach ($expiredConsents as $consent) {
            $this->database->transaction(function () use ($consent, &$count) {
                $consent->is_granted = false;
                $consent->revoked_at = now();

                $this->memberConsentRepository->save($consent);

                $context = ConsentActionContext::fromSystem('Consent expired');

                $this->auditService->log(
                    $consent->member,
                    $consent->consentType,
                    ConsentAction::EXPIRED,
                    true,
                    false,
                    $context
                );

                $count++;
            });
        }

        return $count;
    }

    public function processWithdrawalRequest(
        ConsentWithdrawalRequest $request,
        int                      $adminUserId
    ): bool
    {
        if ($request->status !== ConsentWithdrawalStatus::PENDING->value) {
            throw new ConsentWithdrawalInvalidStateException($request->status);
        }

        return $this->database->transaction(function () use ($request, $adminUserId) {
            $member = $request->member;
            $context = ConsentActionContext::fromAdmin(
                $adminUserId,
                'Withdrawal request processed'
            );

            try {
                $type = ConsentWithdrawalType::from($request->type);

                match ($type) {
                    ConsentWithdrawalType::SPECIFIC_CONSENT =>
                    $this->revokeSpecificConsents($member, $request->consent_types, $context),
                    ConsentWithdrawalType::ALL_MARKETING =>
                    $this->revokeAllMarketing($member, $context),
                    ConsentWithdrawalType::COMPLETE_DELETION =>
                    $this->handleCompleteDeletion($member, $context),
                };

                $request->status = ConsentWithdrawalStatus::COMPLETED->value;
                $request->completed_at = now();
                $request->processed_by = $adminUserId;
                $request->save();

                return true;
            } catch (\Exception $e) {
                $request->status = ConsentWithdrawalStatus::CANCELLED->value;
                $request->notes = 'Error: ' . $e->getMessage();
                $request->save();

                throw $e;
            }
        });
    }

    private function revokeSpecificConsents(
        Member               $member,
        array                $consentCodes,
        ConsentActionContext $context
    ): void
    {
        foreach ($consentCodes as $code) {
            $this->revokeConsent($member, $code, $context);
        }
    }

    private function revokeAllMarketing(Member $member, ConsentActionContext $context): void
    {
        $marketingConsents = $this->consentTypeRepository->findActiveByCategory('marketing');

        foreach ($marketingConsents as $consentType) {
            $this->revokeConsent($member, $consentType->code, $context);
        }
    }

    private function handleCompleteDeletion(Member $member, ConsentActionContext $context): void
    {
        $optionalConsents = $this->consentTypeRepository->findActiveOptional();

        foreach ($optionalConsents as $consentType) {
            if (!$consentType->isRequired()) {
                $this->revokeConsent($member, $consentType->code, $context);
            }
        }
    }
}