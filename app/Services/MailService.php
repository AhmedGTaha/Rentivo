<?php

declare(strict_types=1);

namespace Rentivo\Services;

use PHPMailer\PHPMailer\Exception as MailerException;
use PHPMailer\PHPMailer\PHPMailer;
use Rentivo\Support\Config;
use Rentivo\Support\Logger;
use Rentivo\Support\Str;
use Throwable;

/**
 * SMTP delivery through PHPMailer.
 *
 * Email is strictly best-effort: when SMTP is not configured, or delivery
 * fails, the message is logged and the calling workflow continues. A booking
 * must never fail because a mail server is unavailable.
 */
final class MailService
{
    /** @var list<array{to:string,subject:string,body:string}> Captured in tests. */
    private array $sent = [];

    private bool $captureOnly = false;

    /** Puts the service in capture mode so tests never open a socket. */
    public function captureOnly(bool $capture = true): void
    {
        $this->captureOnly = $capture;
    }

    /** @return list<array{to:string,subject:string,body:string}> */
    public function captured(): array
    {
        return $this->sent;
    }

    public function isConfigured(): bool
    {
        return (string) Config::get('mail.host', '') !== ''
            && (string) Config::get('mail.from_address', '') !== '';
    }

    /**
     * Sends an HTML email.
     *
     * @return bool True when the message was handed to SMTP (or captured).
     */
    public function send(string $toEmail, string $toName, string $subject, string $htmlBody): bool
    {
        if ($this->captureOnly) {
            $this->sent[] = ['to' => $toEmail, 'subject' => $subject, 'body' => $htmlBody];

            return true;
        }

        if (!$this->isConfigured()) {
            Logger::info('Email skipped: SMTP is not configured.', [
                'to'      => $toEmail,
                'subject' => $subject,
            ]);

            return false;
        }

        try {
            $mailer = new PHPMailer(true);
            $mailer->isSMTP();
            $mailer->Host = (string) Config::get('mail.host');
            $mailer->Port = (int) Config::get('mail.port', 587);
            $mailer->CharSet = 'UTF-8';

            $username = (string) Config::get('mail.username', '');

            if ($username !== '') {
                $mailer->SMTPAuth = true;
                $mailer->Username = $username;
                $mailer->Password = (string) Config::get('mail.password', '');
            }

            $encryption = strtolower((string) Config::get('mail.encryption', 'tls'));

            if ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mailer->setFrom(
                (string) Config::get('mail.from_address'),
                (string) Config::get('mail.from_name', 'Rentivo')
            );
            $mailer->addAddress($toEmail, $toName);

            $mailer->isHTML(true);
            $mailer->Subject = $subject;
            $mailer->Body = $htmlBody;
            $mailer->AltBody = trim(strip_tags(str_replace(['<br>', '</p>'], "\n", $htmlBody)));

            $mailer->send();

            return true;
        } catch (MailerException | Throwable $e) {
            // Never propagate: mail failures must not break a workflow.
            Logger::warning('Email delivery failed.', [
                'to'      => $toEmail,
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Wraps a message in the shared Rentivo email shell.
     *
     * @param array{label:string,url:string}|null $action
     */
    public function layout(string $heading, string $bodyHtml, ?array $action = null): string
    {
        $appName = e((string) Config::get('name', 'Rentivo'));

        $button = '';

        if ($action !== null) {
            $button = '<p style="margin:32px 0 0;">
                <a href="' . e($action['url']) . '"
                   style="background:#111111;color:#ffffff;text-decoration:none;padding:14px 28px;
                          border-radius:999px;font-weight:600;display:inline-block;">'
                . e($action['label']) . '</a></p>';
        }

        return '<!doctype html>
<html><body style="margin:0;padding:32px 16px;background:#f4f4f2;
    font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;color:#111111;">
  <div style="max-width:560px;margin:0 auto;background:#ffffff;border:1px solid #e5e5e2;
              border-radius:24px;padding:40px;">
    <p style="margin:0 0 24px;font-size:13px;letter-spacing:0.12em;text-transform:uppercase;color:#777777;">'
        . $appName . '</p>
    <h1 style="margin:0 0 16px;font-size:26px;line-height:1.2;">' . e($heading) . '</h1>
    <div style="font-size:15px;line-height:1.65;color:#3f3f3f;">' . $bodyHtml . '</div>
    ' . $button . '
  </div>
  <p style="max-width:560px;margin:24px auto 0;font-size:12px;color:#999999;text-align:center;">
    You are receiving this because you have a ' . $appName . ' account.
  </p>
</body></html>';
    }

    /** Escapes untrusted text for inclusion in an email body. */
    public function text(?string $value): string
    {
        return Str::escape($value);
    }
}
