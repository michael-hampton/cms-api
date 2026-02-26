<?php

namespace App\Tests\Unit\Services\Shopping;

use App\Framework\Database\Database;
use App\Framework\Date;
use App\Framework\Session\Session;
use App\Repositories\Members\MemberRepository;
use App\Services\Shopping\CartPersistenceService;
use App\Tests\Functional\Controllers\FunctionalTestCase;
use App\Tests\Unit\Repositories\Concerns\CreatesTestData;

class CartPersistenceTest extends FunctionalTestCase
{
    use CreatesTestData;

    private MemberRepository $memberRepository;
    private CartPersistenceService $cartPersistence;
    private string $baseUrl = '/api/site/checkout';

    public function test_it_snapshots_the_cart_before_starting_otp_flow()
    {
        $email = 'test_snapshot@example.com';
        $sessionId = 'session-123';
        $siteId = 1;

        Session::put('cart_items', [
            ['product_id' => 1, 'quantity' => 2],
            ['product_id' => 2, 'quantity' => 1],
        ]);

        $token = $this->cartPersistence->snapshotCartForOTP(
            $email,
            $sessionId,
            $siteId
        );

        $this->assertNotEmpty($token);

        $snapshot = Database::table('cart_snapshots')
            ->where('checkout_token', $token)
            ->first();

        $this->assertNotNull($snapshot);
        $this->assertCount(2, json_decode($snapshot->cart_data, true));
    }

    public function test_it_restores_the_cart_after_successful_authentication()
    {
        $email = 'test_restore@example.com';
        $sessionId = 'session-restore';

        $this->insertSnapshot(
            email: $email,
            sessionId: $sessionId,
            token: 'restore-token',
            cartData: [['product_id' => 3, 'quantity' => 1]]
        );

        $restored = $this->cartPersistence->restoreCartAfterAuth($email, 1);

        $this->assertTrue($restored);
        $this->assertSame(3, $_SESSION['cart'][0]['product_id']);
    }

    private function insertSnapshot(
        string $email,
        string $sessionId,
        string $token,
        array  $cartData,
        int    $siteId = 1,
        ?Date  $expiresAt = null
    ): void
    {
        Database::table('cart_snapshots')->insert([
            'email' => $email,
            'session_id' => $sessionId,
            'checkout_token' => $token,
            'site_id' => $siteId,
            'cart_data' => json_encode($cartData),
            'expires_at' => ($expiresAt ?? now_datetime()->modify('+30 minutes'))->format('Y-m-d H:i:s'),
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }


//    public function test_it_handles_browser_close_and_reopen()
//    {
//        $email     = 'test_browser_close@example.com';
//        $siteId    = 1;
//        $sessionId = 'session-reopen';
//
//        // Member exists (OTP flow requires it)
//        $this->createMember();
//
//        // Cart snapshot exists
//        $this->insertSnapshot(
//            email: $email,
//            sessionId: $sessionId,
//            token: 'reopen-token',
//            cartData: [
//                ['product_id' => 5, 'quantity' => 2],
//                ['product_id' => 6, 'quantity' => 1],
//            ],
//            siteId: $siteId
//        );
//
//        // OTP still pending
//        $this->insertOtp(
//            email: $email,
//            sessionId: $sessionId,
//            siteId: $siteId
//        );
//
//        /**
//         * Simulate "browser reopen":
//         * - new PHP request
//         * - same session id supplied
//         */
//        $_SESSION = [];
//
//        $response = $this->getForSite("/checkout/pending-otp");
//
//        echo '<pre>';
//        print_r($response);
//        die;
//
//        // OTP is still pending
//        $this->assertResponseStatus(200, $response);
//        $this->assertTrue($response['data']['has_pending']);
//        $this->assertSame($email, $response['data']['email']);
//        $this->assertGreaterThan(0, $response['data']['expires_in']);
//
//        // Cart snapshot is untouched
//        $snapshot = Database::table('cart_snapshots')
//            ->where('email', $email)
//            ->first();
//
//        $this->assertNotNull($snapshot);
//    }

    public function test_cart_persists_across_session_expiry()
    {
        $email = 'test_session_expiry@example.com';
        $siteId = 1;

        // Snapshot was created in a previous session
        $this->insertSnapshot(
            email: $email,
            sessionId: 'expired-session-id',
            token: 'session-expiry-token',
            cartData: [
                ['product_id' => 7, 'quantity' => 3],
            ],
            siteId: $siteId
        );

        /**
         * Simulate session expiry:
         * - old session destroyed
         * - brand new session started
         */
        $_SESSION = [];

        // Act
        $restored = $this->cartPersistence->restoreCartAfterAuth($email, $siteId);

        // Assert
        $this->assertTrue($restored);
        $this->assertArrayHasKey('cart', $_SESSION);
        $this->assertSame(7, $_SESSION['cart'][0]['product_id']);
    }

    public function test_expired_snapshots_are_not_restored()
    {
        $email = 'test_expired@example.com';

        $this->insertSnapshot(
            email: $email,
            sessionId: 'old-session',
            token: 'expired-token',
            cartData: [['product_id' => 99]],
            expiresAt: now_datetime()->modify('-5 minutes')
        );

        $restored = $this->cartPersistence->restoreCartAfterAuth($email, 1);

        $this->assertFalse($restored);
        $this->assertArrayNotHasKey('cart', $_SESSION);
    }


    public function test_snapshot_is_deleted_after_successful_restore()
    {
        $email = 'test_cleanup@example.com';

        $this->insertSnapshot(
            email: $email,
            sessionId: 'cleanup-session',
            token: 'cleanup-token',
            cartData: [['product_id' => 9]]
        );

        $this->cartPersistence->restoreCartAfterAuth($email, 1);

        $this->assertNull(
            Database::table('cart_snapshots')
                ->where('checkout_token', 'cleanup-token')
                ->first()
        );
    }


    public function test_cleanup_removes_expired_snapshots()
    {
        // Expired snapshot
        $this->insertSnapshot(
            email: 'expired@test.com',
            sessionId: 'expired-session',
            token: 'expired-token',
            cartData: [],
            expiresAt: now_datetime()->modify('-10 minutes')
        );

        // Valid snapshot
        $this->insertSnapshot(
            email: 'valid@test.com',
            sessionId: 'valid-session',
            token: 'valid-token',
            cartData: [],
            expiresAt: now_datetime()->modify('+20 minutes')
        );

        // Act
        $deleted = $this->cartPersistence->cleanupExpiredSnapshots();

        // Assert - deletion count
        $this->assertSame(1, $deleted);

        // Assert - expired is gone
        $this->assertNull(
            Database::table('cart_snapshots')
                ->where('checkout_token', 'expired-token')
                ->first()
        );

        // Assert - valid remains
        $this->assertNotNull(
            Database::table('cart_snapshots')
                ->where('checkout_token', 'valid-token')
                ->first()
        );
    }

    public function test_empty_cart_snapshot_handles_gracefully()
    {
        $email = 'test_empty_cart@example.com';
        $sessionId = 'empty-cart-session';
        $siteId = 1;

        Session::put('cart', []);

        $token = $this->cartPersistence->snapshotCartForOTP(
            $email,
            $sessionId,
            $siteId
        );

        $this->assertNotEmpty($token);

        // Snapshot should NOT exist
        $snapshot = Database::table('cart_snapshots')
            ->where('checkout_token', $token)
            ->first();

        $this->assertNull($snapshot);
    }


    public function test_get_snapshot_item_count_returns_correct_count()
    {
        $email = 'test_item_count@example.com';
        $siteId = 1;

        $this->insertSnapshot(
            email: $email,
            sessionId: 'count-session',
            token: 'count-token',
            cartData: [
                ['product_id' => 10, 'quantity' => 1],
                ['product_id' => 11, 'quantity' => 2],
                ['product_id' => 12, 'quantity' => 1],
            ],
            siteId: $siteId
        );

        // Act
        $count = $this->cartPersistence->getSnapshotItemCount($email, $siteId);

        // Assert - count line items, not total quantity
        $this->assertSame(3, $count);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $_SESSION = [];

        $this->memberRepository = new MemberRepository();
        $this->cartPersistence = new CartPersistenceService();

        Database::table('members')->where('email', 'like', 'test_%@example.com')->delete();
        Database::table('otp_verifications')->where('email', 'like', 'test_%@example.com')->delete();
        Database::table('cart_snapshots')->where('email', 'like', 'test_%@example.com')->delete();
    }

    private function insertOtp(
        string $email,
        string $sessionId,
        int    $siteId = 1
    ): void
    {
        Database::table('otp_verifications')->insert([
            'email' => $email,
            'otp' => hash('sha256', '123456'),
            'session_id' => $sessionId,
            'site_id' => $siteId,
            'expires_at' => now_datetime()->modify('+5 minutes')->format('Y-m-d H:i:s'),
            'attempts' => 0,
            'verified' => 0,
            'created_at' => now_datetime()->format('Y-m-d H:i:s'),
            'updated_at' => now_datetime()->format('Y-m-d H:i:s'),
        ]);
    }
}