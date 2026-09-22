<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Deliberately tiny: the forum only sends transactional plain-text mail.
 * In development the `log` transport writes to storage/logs/mail.log, which is
 * where you will find password-reset links when no MTA is configured.
 */
final class Mailer
{
    public static function send(string $to, string $subject, string $body): bool
    {
        $from = (string) Config::get('mail.from');
        $fromName = (string) Config::get('mail.from_name');

        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        // Header injection guard.
        $subject = str_replace(["\r", "\n"], ' ', $subject);

        if (Config::get('mail.transport') === 'mail') {
            $headers = [
                'From: ' . sprintf('%s <%s>', $fromName, $from),
                'Content-Type: text/plain; charset=UTF-8',
                'MIME-Version: 1.0',
            ];

            return @mail($to, $subject, $body, implode("\r\n", $headers));
        }

        Logger::write('mail', 'info', 'Outgoing message', [
            'to' => $to,
            'subject' => $subject,
        ]);

        @file_put_contents(
            Config::get('app.paths.logs') . '/mail.log',
            sprintf("--- %s ---\nTo: %s\nSubject: %s\n\n%s\n\n", Dates::nowString(), $to, $subject, $body),
            FILE_APPEND | LOCK_EX,
        );

        return true;
    }
}
