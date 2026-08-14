<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Request;
use App\Core\Session;
use App\Services\NotificationService;

final class ReferralController
{
    public function index(Request $request): void
    {
        $user = Session::get('user');
        $pdo = Database::connection();

        $stmt = $pdo->prepare('SELECT u.id, u.full_name, u.created_at, m.name AS level_name, m.slug AS level_slug,
            (SELECT COUNT(*) FROM deposits d WHERE d.user_id = u.id AND d.status = "approved") AS deposit_count
            FROM referral_relationships rr
            JOIN users u ON u.id = rr.referred_user_id
            LEFT JOIN membership_levels m ON m.id = u.membership_level_id
            WHERE rr.referrer_user_id = ? AND rr.level = 1
            ORDER BY u.created_at DESC');
        $stmt->execute([$user['id']]);
        $team = $stmt->fetchAll();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM referral_relationships WHERE referrer_user_id = ? AND level = 1');
        $stmt->execute([$user['id']]);
        $directCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COUNT(*) FROM referral_relationships WHERE referrer_user_id = ?');
        $stmt->execute([$user['id']]);
        $totalTeamCount = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT COALESCE(SUM(amount),0) FROM referral_rewards WHERE referrer_user_id = ? AND status = "credited"');
        $stmt->execute([$user['id']]);
        $totalReferralEarnings = (string) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT level, bonus_type, bonus_amount, min_qualifying_deposit FROM referral_settings WHERE is_active = 1 ORDER BY level ASC');
        $stmt->execute();
        $levels = $stmt->fetchAll();

        $referralLink = app_url('/register?code=' . $user['referral_code']);

        echo view('layouts.app', [
            'pageTitle' => 'Referrals',
            'unreadCount' => NotificationService::unreadCount((int) $user['id']),
            'content' => view('referral.index', [
                'referralCode' => $user['referral_code'],
                'referralLink' => $referralLink,
                'qrDataUri' => \App\Services\QrCodeService::dataUri($referralLink, 200),
                'welcomeBonus' => setting('welcome_bonus_amount', '20'),
                'team' => $team,
                'directCount' => $directCount,
                'totalTeamCount' => $totalTeamCount,
                'totalReferralEarnings' => $totalReferralEarnings,
                'levels' => $levels,
            ]),
        ]);
    }
}
