<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Rule-based first: this always works even with no AI provider configured.
 * If AI_CHATBOT_API_KEY is set, an AI provider MAY be layered on top for
 * general/FAQ questions only - it never touches wallets, deposits,
 * withdrawals, referrals, or any other financial/account action. Those
 * stay on fixed, predictable flows below.
 */
final class ChatbotService
{
    public static function respond(string $message, ?int $userId): array
    {
        $text = strtolower(trim($message));

        if (self::any($text, ['deposit', 'add fund', 'top up', 'send btc', 'send bitcoin'])) {
            return self::depositReply();
        }
        if (self::any($text, ['withdraw', 'cash out', 'payout'])) {
            return self::withdrawReply();
        }
        if (self::any($text, ['cashback', 'cash back'])) {
            return self::cashbackReply();
        }
        if (self::any($text, ['referral', 'invite', 'refer a friend', 'invitation code'])) {
            return self::referralReply();
        }
        if (self::any($text, ['order', 'purchase', 'tracking'])) {
            return [
                'reply' => "Orders are recorded once your marketplace purchase is verified. Track their status any time on the Orders page - cashback is credited once an order is confirmed or completed.",
                'quick_replies' => ['How do I deposit?', 'How does cashback work?'],
            ];
        }
        if (self::any($text, ['password', 'security', 'account', 'login', 'private key', 'seed phrase'])) {
            return [
                'reply' => "You can manage your password and security from the Profile page. Important: we will NEVER ask for your private key, seed phrase, or wallet password - only your public BTC address is ever needed.",
                'quick_replies' => ['How do I deposit?', 'How do withdrawals work?'],
            ];
        }
        if (self::any($text, ['membership', 'level', 'bronze', 'silver', 'gold', 'platinum'])) {
            return [
                'reply' => "Membership levels (Bronze, Silver, Gold, Platinum) unlock better cashback and referral multipliers as your total deposits and purchases grow. Check your progress on your Dashboard.",
                'quick_replies' => ['How does cashback work?', 'Referral program'],
            ];
        }

        $faq = self::searchFaq($text);
        if ($faq) {
            return ['reply' => $faq['answer'], 'quick_replies' => self::defaultQuickReplies()];
        }

        return [
            'reply' => "I can help with deposits, withdrawals, cashback, referrals, orders, and account questions. What would you like to know?",
            'quick_replies' => self::defaultQuickReplies(),
        ];
    }

    private static function any(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, $needle)) {
                return true;
            }
        }
        return false;
    }

    private static function defaultQuickReplies(): array
    {
        return ['How do I deposit?', 'How do withdrawals work?', 'How does cashback work?', 'Referral program'];
    }

    private static function depositReply(): array
    {
        $address = (string) setting('btc_deposit_address', '');
        $min = money((string) setting('min_deposit_usd', '50'));

        $reply = $address
            ? "To deposit: go to Wallet &gt; Deposit, send BTC to <strong>{$address}</strong>, then submit your transaction hash (TXID). "
              . "An admin manually verifies your transaction and credits the USD equivalent to your wallet. Minimum deposit: {$min}."
            : "Deposits are configured by an admin. Visit Wallet &gt; Deposit for the current BTC address and instructions.";

        return ['reply_html' => $reply, 'quick_replies' => ['I have sent BTC', 'How long does approval take?', 'How does cashback work?']];
    }

    private static function withdrawReply(): array
    {
        $min = money((string) setting('min_withdrawal_usd', '1000'));
        return [
            'reply' => "Minimum withdrawal is {$min}. Submit a request from Wallet &gt; Withdraw with your BTC address. "
                . "An admin reviews and manually processes the BTC payment through this flow: Requested -> Approved -> Processing -> Paid -> Completed.",
            'quick_replies' => ['How do I deposit?', 'How does cashback work?'],
        ];
    }

    private static function cashbackReply(): array
    {
        return [
            'reply' => "Every eligible product shows a cashback percentage or fixed amount. Once your order is confirmed, cashback is credited to your wallet - track it any time on your Wallet page.",
            'quick_replies' => ['How do withdrawals work?', 'Referral program'],
        ];
    }

    private static function referralReply(): array
    {
        $welcome = money((string) setting('welcome_bonus_amount', '20'));
        return [
            'reply' => "Share your referral code or link from the Rewards page. New members get a {$welcome} welcome bonus, and you earn a referral bonus once they make a qualifying deposit.",
            'quick_replies' => ['How do I deposit?', 'How does cashback work?'],
        ];
    }

    private static function searchFaq(string $text): ?array
    {
        $pdo = Database::connection();
        $stmt = $pdo->prepare('SELECT * FROM faq WHERE is_active = 1 AND (LOWER(question) LIKE ? OR LOWER(answer) LIKE ?) LIMIT 1');
        $like = '%' . substr($text, 0, 60) . '%';
        $stmt->execute([$like, $like]);
        return $stmt->fetch() ?: null;
    }

    public static function logMessage(?int $userId, string $sessionId, string $sender, string $message, ?string $intent = null): void
    {
        Database::connection()
            ->prepare('INSERT INTO chat_messages (user_id, session_id, sender, message, intent) VALUES (?,?,?,?,?)')
            ->execute([$userId, $sessionId, $sender, $message, $intent]);
    }
}
