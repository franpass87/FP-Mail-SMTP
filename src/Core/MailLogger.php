<?php
/**
 * Logger per email in uscita.
 *
 * Ascolta wp_mail_succeeded e wp_mail_failed e registra in DB.
 *
 * @package FP\Fpmail\Core
 */

declare(strict_types=1);

namespace FP\Fpmail\Core;

use WP_Error;

/**
 * Registra tutte le email inviate/fallite nella tabella fp_fpmail_logs.
 */
final class MailLogger
{
    private const PREVIEW_LENGTH = 500;

    /** Limite dimensione corpo HTML salvato (byte), per evitare righe enormi in DB. */
    private const MAX_BODY_BYTES = 524288;

    /**
     * Registra gli hook.
     */
    public function register(): void
    {
        add_action('wp_mail_succeeded', [$this, 'onMailSucceeded'], 10, 1);
        add_action('wp_mail_failed', [$this, 'onMailFailed'], 10, 1);
    }

    /**
     * Callback per email inviate con successo.
     *
     * @param array<string, mixed> $mail_data Dati email da wp_mail_succeeded.
     */
    public function onMailSucceeded(array $mail_data): void
    {
        if (get_option('fp_fpmail_log_enabled', '1') !== '1') {
            return;
        }
        $this->insertLog($mail_data, 'sent', '');
    }

    /**
     * Callback per email fallite.
     *
     * @param WP_Error $error Errore da wp_mail_failed.
     */
    public function onMailFailed(WP_Error $error): void
    {
        if (get_option('fp_fpmail_log_enabled', '1') !== '1') {
            return;
        }
        $mail_data = $error->get_error_data();
        $msg = $error->get_error_message();
        if (is_array($mail_data)) {
            $this->insertLog($mail_data, 'failed', $msg);
        }
    }

    /**
     * Inserisce un record nel log.
     *
     * @param array<string, mixed> $mail_data Dati email.
     * @param string $status 'sent' o 'failed'.
     * @param string $error_message Messaggio errore se failed.
     */
    private function insertLog(array $mail_data, string $status, string $error_message): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_fpmail_logs';

        $to = $mail_data['to'] ?? [];
        $toAddresses = is_array($to) ? implode(', ', $to) : (string) $to;
        $toAddresses = sanitize_text_field($toAddresses);

        $subject = isset($mail_data['subject'])
            ? str_replace(["\r", "\n"], '', sanitize_text_field((string) $mail_data['subject']))
            : '';
        $message = isset($mail_data['message']) ? (string) $mail_data['message'] : '';
        $messageBody = $message;
        if (strlen($messageBody) > self::MAX_BODY_BYTES) {
            $messageBody = substr($messageBody, 0, self::MAX_BODY_BYTES);
        }
        $messagePreview = sanitize_textarea_field(wp_strip_all_tags(mb_substr($message, 0, self::PREVIEW_LENGTH)));

        $headers = $mail_data['headers'] ?? [];
        $headersStr = is_array($headers) ? wp_json_encode($headers) : (string) $headers;
        $headersStr = sanitize_text_field($headersStr);

        $attachments = $mail_data['attachments'] ?? [];
        $attachmentsCount = is_array($attachments) ? count($attachments) : 0;

        // From: estraiamo dall'header o usiamo admin_email
        $fromEmail = $this->extractFromEmail($mail_data);

        $wpdb->insert(
            $table,
            [
                'to_addresses' => $toAddresses,
                'from_email' => $fromEmail,
                'subject' => mb_substr($subject, 0, 500),
                'message_preview' => $messagePreview,
                'message_body' => $messageBody,
                'headers' => mb_substr($headersStr, 0, 4096),
                'attachments_count' => $attachmentsCount,
                'status' => $status === 'failed' ? 'failed' : 'sent',
                'error_message' => sanitize_textarea_field($error_message),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );
    }

    /**
     * Estrae l'email mittente dai dati (headers o fallback).
     *
     * @param array<string, mixed> $mail_data
     * @return string
     */
    private function extractFromEmail(array $mail_data): string
    {
        $from = get_option('fp_fpmail_from_email', '');
        if ($from !== '' && is_email($from)) {
            return $from;
        }
        $headers = $mail_data['headers'] ?? [];
        if (is_array($headers)) {
            foreach ($headers as $h) {
                if (is_string($h) && stripos($h, 'From:') === 0) {
                    $email = trim(substr($h, 5));
                    if (preg_match('/<([^>]+)>/', $email, $m)) {
                        return sanitize_email($m[1]);
                    }
                    return sanitize_email($email);
                }
            }
        }
        return sanitize_email(get_option('admin_email', ''));
    }
}
