<?php
/**
 * Inserimento log email da eventi Brevo (webhook o API statistics).
 *
 * @package FP\Fpmail\Brevo
 */

declare(strict_types=1);

namespace FP\Fpmail\Brevo;

/**
 * Normalizza nomi evento API (`requests`, `hardBounces`) verso forma webhook (`request`, `hardBounce`).
 */
final class BrevoLogIngestor
{
    private const SENT_EVENTS = ['request'];

    private const FAILED_EVENTS = ['hardBounce', 'softBounce', 'blocked', 'invalid', 'error'];

    private const DELIVERED_EVENTS = ['delivered'];

    /**
     * Mappa tipo evento da GET /smtp/statistics/events verso nome stile webhook.
     */
    public static function normalize_api_event_type(string $apiEvent): string
    {
        return match ($apiEvent) {
            'requests' => 'request',
            'hardBounces' => 'hardBounce',
            'softBounces' => 'softBounce',
            'bounces' => 'hardBounce',
            default => $apiEvent,
        };
    }

    /**
     * @return string|null 'sent', 'failed' o null se ignorato
     */
    public static function map_event_to_status(string $canonicalEvent): ?string
    {
        if (in_array($canonicalEvent, self::SENT_EVENTS, true)) {
            return 'sent';
        }
        if (in_array($canonicalEvent, self::FAILED_EVENTS, true)) {
            return 'failed';
        }
        if (in_array($canonicalEvent, self::DELIVERED_EVENTS, true)) {
            return 'sent';
        }

        return null;
    }

    /**
     * Elabora una riga dalla API eventi Brevo (campi camelCase).
     *
     * @param array<string, mixed> $row
     */
    public static function ingest_api_event_row(array $row, ?callable $tagFilter = null): void
    {
        $rawEvent = isset($row['event']) ? (string) $row['event'] : '';
        if ($rawEvent === '') {
            return;
        }

        $canonical = self::normalize_api_event_type($rawEvent);
        $status = self::map_event_to_status($canonical);
        if ($status === null) {
            return;
        }

        if ($tagFilter !== null && !$tagFilter($row)) {
            return;
        }

        $email = isset($row['email']) ? sanitize_email((string) $row['email']) : '';
        if ($email === '') {
            return;
        }

        $subject = isset($row['subject'])
            ? str_replace(["\r", "\n"], '', sanitize_text_field((string) $row['subject']))
            : '';
        $messageId = isset($row['messageId']) ? sanitize_text_field((string) $row['messageId']) : '';
        $dateRaw = isset($row['date']) ? (string) $row['date'] : '';
        $reason = isset($row['reason']) ? (string) $row['reason'] : '';

        self::persist($canonical, $email, $subject, $messageId, $dateRaw, $reason, '');
    }

    /**
     * Elabora payload webhook (chiavi snake/kebab come da Brevo).
     *
     * @param array<string, mixed> $body
     *
     * @return array{ok:bool,error?:string,ignored?:string,updated?:int}
     */
    public static function ingest_webhook_body(array $body): array
    {
        $event = isset($body['event']) ? (string) $body['event'] : '';
        $email = isset($body['email']) ? sanitize_email((string) $body['email']) : '';

        if ($event === '' || $email === '') {
            return ['ok' => false, 'error' => 'Missing event or email'];
        }

        $status = self::map_event_to_status($event);
        if ($status === null) {
            return ['ok' => true, 'ignored' => $event];
        }

        $subject = isset($body['subject'])
            ? str_replace(["\r", "\n"], '', sanitize_text_field((string) $body['subject']))
            : '';
        $messageId = isset($body['message-id']) ? sanitize_text_field((string) $body['message-id']) : '';
        $date = isset($body['date']) ? (string) $body['date'] : '';
        $reason = isset($body['reason']) ? (string) $body['reason'] : '';
        $mirrorLink = isset($body['mirror_link']) ? (string) $body['mirror_link'] : '';

        self::persist($event, $email, $subject, $messageId, $date, $reason, $mirrorLink);

        return ['ok' => true];
    }

    private static function persist(
        string $canonicalEvent,
        string $email,
        string $subject,
        string $messageId,
        string $dateRaw,
        string $reason,
        string $mirrorLink
    ): void {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_fpmail_logs';

        $status = self::map_event_to_status($canonicalEvent);
        if ($status === null) {
            return;
        }

        $errorMessage = $status === 'failed' ? ($reason !== '' ? sanitize_textarea_field($reason) : ucfirst($canonicalEvent)) : '';

        if (in_array($canonicalEvent, self::DELIVERED_EVENTS, true) && $messageId !== '') {
            $updated = $wpdb->update(
                $table,
                [
                    'status' => 'sent',
                    'brevo_event' => sanitize_text_field($canonicalEvent),
                ],
                [
                    'brevo_message_id' => $messageId,
                    'source' => 'brevo',
                ],
                ['%s', '%s'],
                ['%s', '%s']
            );
            if ($updated > 0) {
                return;
            }
        }

        if (self::log_row_exists($table, $messageId, $canonicalEvent, $email)) {
            return;
        }

        $createdAt = $dateRaw !== '' ? self::parse_brevo_datetime($dateRaw) : current_time('mysql');

        $wpdb->insert(
            $table,
            [
                'to_addresses' => $email,
                'from_email' => '',
                'subject' => mb_substr($subject, 0, 500),
                'message_preview' => '',
                'message_body' => '',
                'headers' => $mirrorLink !== '' && esc_url_raw($mirrorLink) === $mirrorLink
                    ? 'mirror_link: ' . esc_url_raw($mirrorLink)
                    : '',
                'attachments_count' => 0,
                'status' => $status,
                'error_message' => $errorMessage,
                'source' => 'brevo',
                'brevo_event' => sanitize_text_field($canonicalEvent),
                'brevo_message_id' => mb_substr($messageId, 0, 255),
                'created_at' => $createdAt,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * @param string $table Nome tabella completo con prefisso.
     */
    private static function log_row_exists(string $table, string $messageId, string $event, string $email): bool
    {
        if ($messageId === '') {
            return false;
        }

        global $wpdb;

        $n = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE source = %s AND brevo_message_id = %s AND brevo_event = %s AND to_addresses = %s",
                'brevo',
                $messageId,
                $event,
                $email
            )
        );

        return $n > 0;
    }

    /**
     * Data/ora Brevo (ISO o Y-m-d H:i:s) → MySQL UTC.
     */
    private static function parse_brevo_datetime(string $date): string
    {
        $ts = strtotime($date);
        if ($ts === false) {
            return current_time('mysql');
        }

        return gmdate('Y-m-d H:i:s', $ts);
    }
}
