<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\DepositService;
use App\Services\ReferralService;
use App\Services\WalletService;
use App\Support\ValidationException;
use Tests\TestCase;
use Tests\TestSeed;

final class ReferralServiceTest extends TestCase
{
    public function test_welcome_bonus_is_credited_once(): void
    {
        $user = TestSeed::createUser('ref-welcome');

        $first = ReferralService::awardWelcomeBonus($user['id'], '127.0.0.1');
        $second = ReferralService::awardWelcomeBonus($user['id'], '127.0.0.1');

        $this->assertTrue($first);
        $this->assertFalse($second, 'Second call must be a no-op, not a duplicate credit');

        $wallet = WalletService::getOrCreateWallet($user['id'], Database::connection());
        $this->assertSame('20.00', $wallet['referral_balance']);
    }

    public function test_invalid_invitation_code_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        ReferralService::validateInvitationCode('THIS-CODE-DOES-NOT-EXIST', Database::connection());
    }

    public function test_deposit_triggered_referral_bonus_pays_referrer_once(): void
    {
        $referrer = TestSeed::createUser('ref-parent');
        $referred = TestSeed::createUser('ref-child', $referrer['referral_code']);

        $deposit = DepositService::submit($referred['id'], '0.01', bin2hex(random_bytes(20)), '127.0.0.1');
        DepositService::approve($deposit['id'], 1, '60000.00', '127.0.0.1');

        $referrerWallet = WalletService::getOrCreateWallet($referrer['id'], Database::connection());
        $this->assertSame('20.00', $referrerWallet['referral_balance'], 'Level-1 referral bonus should be credited once');

        // Calling the awarding routine again directly (simulating a retried job)
        // must not double-pay - guarded by the DB unique key, not app logic alone.
        ReferralService::awardDepositBonuses($referred['id'], '600.00', $deposit['id'], '127.0.0.1');
        $referrerWallet = WalletService::getOrCreateWallet($referrer['id'], Database::connection());
        $this->assertSame('20.00', $referrerWallet['referral_balance'], 'Re-running the award routine must not double-pay');
    }

    public function test_deposit_below_minimum_qualifying_amount_pays_no_bonus(): void
    {
        $referrer = TestSeed::createUser('ref-parent-small');
        $referred = TestSeed::createUser('ref-child-small', $referrer['referral_code']);

        $deposit = DepositService::submit($referred['id'], '0.0001', bin2hex(random_bytes(20)), '127.0.0.1');
        DepositService::approve($deposit['id'], 1, '100.00', '127.0.0.1'); // 0.0001 * 100 = $0.01, below $50 minimum

        $referrerWallet = WalletService::getOrCreateWallet($referrer['id'], Database::connection());
        $this->assertSame('0.00', $referrerWallet['referral_balance']);
    }

    public function test_self_referral_relationship_is_never_created(): void
    {
        $user = TestSeed::createUser('ref-self');
        ReferralService::buildRelationships($user['id'], $user['id'], Database::connection());

        $stmt = Database::connection()->prepare('SELECT COUNT(*) FROM referral_relationships WHERE referrer_user_id = ? AND referred_user_id = ?');
        $stmt->execute([$user['id'], $user['id']]);
        $this->assertSame(0, (int) $stmt->fetchColumn());
    }
}
