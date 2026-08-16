<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Database;
use App\Services\AdService;
use App\Services\WalletService;
use PDO;
use RuntimeException;
use Tests\TestCase;
use Tests\TestSeed;

final class AdServiceTest extends TestCase
{
    private function makeAd(PDO $pdo, array $overrides = []): int
    {
        $defaults = [
            'title' => 'Test Ad ' . bin2hex(random_bytes(3)),
            'description' => 'A test advertisement',
            'type' => 'image',
            'reward_amount' => '0.25',
            'watch_seconds' => 30,
            'max_completions' => null,
            'is_active' => 1,
        ];
        $data = array_merge($defaults, $overrides);

        $stmt = $pdo->prepare('INSERT INTO advertisements (title, description, type, reward_amount, watch_seconds, max_completions, is_active)
            VALUES (?,?,?,?,?,?,?)');
        $stmt->execute([
            $data['title'], $data['description'], $data['type'], $data['reward_amount'],
            $data['watch_seconds'], $data['max_completions'], $data['is_active'],
        ]);

        return (int) $pdo->lastInsertId();
    }

    public function test_inactive_ad_cannot_start_session(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('ad-inactive');
        $adId = $this->makeAd($pdo, ['is_active' => 0]);

        $this->expectException(RuntimeException::class);
        AdService::startSession((int) $user['id'], $adId, '127.0.0.1');
    }

    public function test_completion_before_watch_duration_is_rejected(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('ad-early');
        $adId = $this->makeAd($pdo, ['watch_seconds' => 30, 'reward_amount' => '0.25']);

        $session = AdService::startSession((int) $user['id'], $adId, '127.0.0.1');

        $this->expectException(RuntimeException::class);
        AdService::completeSession((int) $user['id'], $session['session_uuid'], '127.0.0.1');
    }

    public function test_valid_completion_credits_exact_reward_via_ledger(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('ad-valid');
        $adId = $this->makeAd($pdo, ['watch_seconds' => 30, 'reward_amount' => '0.25']);

        $session = AdService::startSession((int) $user['id'], $adId, '127.0.0.1');
        $pdo->prepare('UPDATE ad_watch_sessions SET started_at = DATE_SUB(NOW(), INTERVAL 60 SECOND) WHERE session_uuid = ?')
            ->execute([$session['session_uuid']]);

        $result = AdService::completeSession((int) $user['id'], $session['session_uuid'], '127.0.0.1');
        $this->assertSame('0.25', $result['reward_amount']);

        $wallet = WalletService::getOrCreateWallet((int) $user['id'], $pdo);
        $this->assertSame('0.25', $wallet['referral_balance']);

        $stmt = $pdo->prepare("SELECT amount, type, balance_field FROM wallet_ledger WHERE user_id = ? AND type = 'ad_reward'");
        $stmt->execute([$user['id']]);
        $ledger = $stmt->fetch();
        $this->assertSame('0.25', $ledger['amount']);
        $this->assertSame('referral', $ledger['balance_field']);
    }

    public function test_duplicate_completion_is_rejected(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('ad-duplicate');
        $adId = $this->makeAd($pdo, ['watch_seconds' => 30, 'reward_amount' => '0.25']);

        $session = AdService::startSession((int) $user['id'], $adId, '127.0.0.1');
        $pdo->prepare('UPDATE ad_watch_sessions SET started_at = DATE_SUB(NOW(), INTERVAL 60 SECOND) WHERE session_uuid = ?')
            ->execute([$session['session_uuid']]);
        AdService::completeSession((int) $user['id'], $session['session_uuid'], '127.0.0.1');

        // A fresh watch session for the same ad should be blocked before it even starts...
        $this->expectException(RuntimeException::class);
        AdService::startSession((int) $user['id'], $adId, '127.0.0.1');
    }

    public function test_duplicate_completion_blocked_even_via_direct_session_reuse(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('ad-duplicate-reuse');
        $adId = $this->makeAd($pdo, ['watch_seconds' => 30, 'reward_amount' => '0.25']);

        $session = AdService::startSession((int) $user['id'], $adId, '127.0.0.1');
        $pdo->prepare('UPDATE ad_watch_sessions SET started_at = DATE_SUB(NOW(), INTERVAL 60 SECOND) WHERE session_uuid = ?')
            ->execute([$session['session_uuid']]);
        AdService::completeSession((int) $user['id'], $session['session_uuid'], '127.0.0.1');

        // Re-marking the already-completed session "active" and replaying completion
        // must still be blocked by the ad_completions unique constraint.
        $pdo->prepare('UPDATE ad_watch_sessions SET status = "active" WHERE session_uuid = ?')->execute([$session['session_uuid']]);

        $this->expectException(RuntimeException::class);
        AdService::completeSession((int) $user['id'], $session['session_uuid'], '127.0.0.1');
    }

    public function test_expired_session_is_rejected(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('ad-expired');
        $adId = $this->makeAd($pdo, ['watch_seconds' => 5, 'reward_amount' => '0.10']);

        $session = AdService::startSession((int) $user['id'], $adId, '127.0.0.1');
        $pdo->prepare('UPDATE ad_watch_sessions SET started_at = DATE_SUB(NOW(), INTERVAL 1 DAY), expires_at = DATE_SUB(NOW(), INTERVAL 1 HOUR) WHERE session_uuid = ?')
            ->execute([$session['session_uuid']]);

        $this->expectException(RuntimeException::class);
        AdService::completeSession((int) $user['id'], $session['session_uuid'], '127.0.0.1');
    }

    public function test_reward_amount_is_snapshotted_and_immune_to_later_ad_edits(): void
    {
        $pdo = Database::connection();
        $user = TestSeed::createUser('ad-snapshot');
        $adId = $this->makeAd($pdo, ['watch_seconds' => 30, 'reward_amount' => '0.25']);

        $session = AdService::startSession((int) $user['id'], $adId, '127.0.0.1');

        // Admin edits the reward upward after the session already started.
        $pdo->prepare('UPDATE advertisements SET reward_amount = ? WHERE id = ?')->execute(['100.00', $adId]);
        $pdo->prepare('UPDATE ad_watch_sessions SET started_at = DATE_SUB(NOW(), INTERVAL 60 SECOND) WHERE session_uuid = ?')
            ->execute([$session['session_uuid']]);

        $result = AdService::completeSession((int) $user['id'], $session['session_uuid'], '127.0.0.1');
        $this->assertSame('0.25', $result['reward_amount'], 'Completion must honor the reward locked in at session start, not a later edit.');
    }

    public function test_max_completions_cap_is_enforced(): void
    {
        $pdo = Database::connection();
        $adId = $this->makeAd($pdo, ['watch_seconds' => 1, 'reward_amount' => '0.10', 'max_completions' => 1]);

        $userA = TestSeed::createUser('ad-cap-a');
        $sessionA = AdService::startSession((int) $userA['id'], $adId, '127.0.0.1');
        $pdo->prepare('UPDATE ad_watch_sessions SET started_at = DATE_SUB(NOW(), INTERVAL 60 SECOND) WHERE session_uuid = ?')
            ->execute([$sessionA['session_uuid']]);
        AdService::completeSession((int) $userA['id'], $sessionA['session_uuid'], '127.0.0.1');

        $userB = TestSeed::createUser('ad-cap-b');
        $this->expectException(RuntimeException::class);
        AdService::startSession((int) $userB['id'], $adId, '127.0.0.1');
    }

    public function test_user_cannot_complete_another_users_watch_session(): void
    {
        $pdo = Database::connection();
        $victim = TestSeed::createUser('ad-idor-victim');
        $attacker = TestSeed::createUser('ad-idor-attacker');
        $adId = $this->makeAd($pdo, ['watch_seconds' => 30, 'reward_amount' => '5.00']);

        $session = AdService::startSession((int) $victim['id'], $adId, '127.0.0.1');
        $pdo->prepare('UPDATE ad_watch_sessions SET started_at = DATE_SUB(NOW(), INTERVAL 60 SECOND) WHERE session_uuid = ?')
            ->execute([$session['session_uuid']]);

        $this->expectException(RuntimeException::class);
        AdService::completeSession((int) $attacker['id'], $session['session_uuid'], '127.0.0.1');
    }

    public function test_active_ads_excludes_ad_already_full_but_keeps_it_for_completed_user(): void
    {
        $pdo = Database::connection();
        $adId = $this->makeAd($pdo, ['watch_seconds' => 1, 'reward_amount' => '0.10', 'max_completions' => 1]);

        $userA = TestSeed::createUser('ad-list-a');
        $sessionA = AdService::startSession((int) $userA['id'], $adId, '127.0.0.1');
        $pdo->prepare('UPDATE ad_watch_sessions SET started_at = DATE_SUB(NOW(), INTERVAL 60 SECOND) WHERE session_uuid = ?')
            ->execute([$sessionA['session_uuid']]);
        AdService::completeSession((int) $userA['id'], $sessionA['session_uuid'], '127.0.0.1');

        $userB = TestSeed::createUser('ad-list-b');
        $listForB = AdService::activeAds((int) $userB['id']);
        $this->assertFalse(in_array($adId, array_column($listForB, 'id'), true), 'A full ad must not be offered to a user who has not completed it.');

        $listForA = AdService::activeAds((int) $userA['id']);
        $adForA = current(array_filter($listForA, fn ($a) => (int) $a['id'] === $adId));
        $this->assertNotFalse($adForA, 'The completing user should still see the ad (marked completed), not have it vanish.');
        $this->assertTrue($adForA['user_completed']);
    }
}
