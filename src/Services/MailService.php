<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Logger;
use PHPMailer\PHPMailer\PHPMailer;

final class MailService
{
    public static function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        if (env('MAIL_MAILER', 'smtp') === 'log') {
            Logger::info('Mail (log driver, not actually sent)', [
                'to' => $toEmail,
                'subject' => $subject,
                'body_html' => $htmlBody,
            ]);
            return true;
        }

        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = (string) env('MAIL_HOST');
            $mailer->SMTPAuth = true;
            $mailer->Username = (string) env('MAIL_USERNAME');
            $mailer->Password = (string) env('MAIL_PASSWORD');
            $mailer->SMTPSecure = (string) env('MAIL_ENCRYPTION', 'ssl');
            $mailer->Port = (int) env('MAIL_PORT', 465);
            $mailer->CharSet = 'UTF-8';
            $mailer->Timeout = 10;

            $mailer->setFrom((string) env('MAIL_FROM_ADDRESS'), (string) env('MAIL_FROM_NAME', 'Billions Earn'));
            $mailer->addAddress($toEmail, $toName);
            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $htmlBody));

            $mailer->send();
            return true;
        } catch (\Throwable $e) {
            Logger::error('Mail send failed: ' . $e->getMessage(), ['to' => $toEmail, 'subject' => $subject]);
            return false;
        }
    }

    public static function layout(string $title, string $bodyHtml): string
    {
        $siteName = e((string) setting('site_name', 'Billions Earn'));
        return <<<HTML
        <div style="background:#0a0a0f;padding:32px 16px;font-family:-apple-system,Segoe UI,Roboto,sans-serif;">
          <div style="max-width:520px;margin:0 auto;background:#131320;border-radius:16px;padding:32px;border:1px solid #26263a;">
            <h2 style="color:#e8e8f5;margin:0 0 16px;">{$siteName}</h2>
            <div style="color:#c4c4d8;font-size:15px;line-height:1.6;">{$bodyHtml}</div>
            <p style="color:#6b6b85;font-size:12px;margin-top:32px;">If you did not request this, you can safely ignore this email.</p>
          </div>
        </div>
        HTML;
    }
}
