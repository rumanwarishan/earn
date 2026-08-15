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

    /**
     * Table-based layout with inline styles throughout - deliberately not
     * relying on backdrop-filter/CSS variables/flexbox, none of which are
     * reliable across email clients (Outlook desktop in particular).
     */
    public static function layout(string $title, string $bodyHtml): string
    {
        $siteName = e((string) setting('site_name', 'Billions Earn'));
        $supportEmail = e((string) setting('support_email', 'support@billionsstore.com'));
        $year = date('Y');

        return <<<HTML
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#05050a;padding:40px 16px;font-family:-apple-system,Segoe UI,Roboto,Helvetica,Arial,sans-serif;">
          <tr><td align="center">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:520px;background:#12121f;border-radius:20px;overflow:hidden;border:1px solid #262640;">
              <tr>
                <td style="background-color:#5b7bff;background-image:linear-gradient(135deg,#3ecbff,#9b6bff);padding:30px 32px;text-align:center;">
                  <div style="font-size:30px;line-height:1;">🐇💸</div>
                  <div style="color:#04060f;font-weight:800;font-size:19px;margin-top:10px;letter-spacing:0.01em;">{$siteName}</div>
                </td>
              </tr>
              <tr>
                <td style="padding:36px 32px 12px;">
                  <div style="color:#e8e8f5;font-size:15px;line-height:1.75;">{$bodyHtml}</div>
                </td>
              </tr>
              <tr>
                <td style="padding:8px 32px 28px;">
                  <div style="border-top:1px solid #262640;padding-top:18px;">
                    <p style="color:#6b6b85;font-size:12px;line-height:1.6;margin:0;">
                      If you did not request this, you can safely ignore this email.
                      Need help? Contact <a href="mailto:{$supportEmail}" style="color:#3ecbff;text-decoration:none;">{$supportEmail}</a>.
                    </p>
                  </div>
                </td>
              </tr>
            </table>
            <p style="color:#4a4a5e;font-size:11px;margin:20px 0 0;">&copy; {$year} {$siteName}. This is an automated message - please don't reply directly.</p>
          </td></tr>
        </table>
        HTML;
    }

    /** Bulletproof table-based button - renders consistently even in Outlook desktop. */
    public static function button(string $url, string $label): string
    {
        $url = e($url);
        $label = e($label);
        return <<<HTML
        <table role="presentation" cellpadding="0" cellspacing="0" style="margin:22px 0;">
          <tr>
            <td style="background-color:#3ecbff;background-image:linear-gradient(135deg,#3ecbff,#5b7bff);border-radius:12px;">
              <a href="{$url}" style="display:inline-block;padding:13px 28px;color:#04060f;font-weight:700;font-size:14.5px;text-decoration:none;">{$label}</a>
            </td>
          </tr>
        </table>
        HTML;
    }
}
