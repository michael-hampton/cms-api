<?php

declare(strict_types=1);

namespace App\Services\MerchantPortal;

use App\Framework\Database\Database;
use App\Framework\Support\Str;
use App\Models\Model;
use App\Models\User;
use App\Repositories\Cms\UserRepositoryInterface;
use App\Repositories\Product\MerchantContactRepository;
use App\Repositories\Product\MerchantRepository;
use RuntimeException;

/**
 * Handles merchant portal self-registration.
 *
 * Flow:
 *   1. Check merchant_contacts for supplied email.
 *   2a. Found  → use the linked merchant; create a new portal User scoped to that merchant.
 *   2b. Not found → create a new Merchant record, then create the portal User.
 *   3. Return the new User.
 *
 * Wrapped in a single transaction – any failure rolls everything back.
 */
final class MerchantPortalRegistrationService
{
    public function __construct(
        private readonly MerchantContactRepository $contactRepository,
        private readonly MerchantRepository        $merchantRepository,
        private readonly UserRepositoryInterface   $userRepository,
        private readonly Database                  $database
    )
    {
    }

    /**
     * @param array{
     *   email: string,
     *   name: string,
     *   password: string,
     *   company_name?: string,
     *   phone?: string,
     * } $data
     *
     * @throws RuntimeException if the email already has a portal user account.
     */
    public function register(array $data): User
    {
        return $this->database->transaction(function () use ($data): Model {
            $email = strtolower(trim($data['email']));
            $name = trim($data['name']);
            $password = $data['password'];
            $phone = $data['phone'] ?? null;
            $companyName = $data['company_name'] ?? $name;

            // Guard: no duplicate portal accounts.
            $existing = $this->userRepository->findByEmail($email, null);

            if ($existing) {
                throw new RuntimeException('An account with this email address already exists.');
            }

            $merchantId = $this->resolveOrCreateMerchant($email, $companyName, $name, $phone);

            return $this->userRepository->create([
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => 'admin',
                'merchant_id' => $merchantId,
                'is_active' => true,
            ]);
        });
    }

    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Returns an existing merchant_id when the email is a known contact,
     * otherwise creates a new Merchant and returns its id.
     */
    private function resolveOrCreateMerchant(
        string  $email,
        string  $companyName,
        string  $name,
        ?string $phone,
    ): int
    {
        $contact = $this->contactRepository->findByEmail($email);

        if ($contact && $contact->merchant_id) {
            return (int)$contact->merchant_id;
        }

        $merchant = $this->merchantRepository->create([
            'name' => $companyName,
            'slug' => Str::slug($companyName) . '-' . substr(uniqid(), -4),
            //'phone'     => $phone,
            'is_active' => false, // pending review by admin
        ]);

        $contact = $this->contactRepository->create([
            'merchant_id' => $merchant->id,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'role' => 'admin'
        ]);

        return (int)$merchant->id;
    }
}