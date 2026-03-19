<?php
/**
 * Controller REST per webhook Brevo.
 *
 * Riceve eventi transactional (sent, delivered, bounce, ecc.) e li salva nel log.
 *
 * @package FP\Fpmail\Api
 */

declare(strict_types=1);

namespace FP\Fpmail\Api;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handler per i webhook Brevo transactional.
 */
final class BrevoWebhookController
{
    private const NAMESPACE = 'fp/fpmail/v1';

    private const SENT_EVENTS = ['request'];

    private const FAILED_EVENTS = ['hardBounce', 'softBounce', 'blocked', 'invalid', 'error'];

    private const DELIVERED_EVENTS = ['delivered'];

    /**
     * Registra le route REST.
     */
    public function register_routes(): void
    {
        register_rest_route(self::NAMESPACE, '/brevo-webhook', [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'handle_webhook'],
            'permission_callback' => [$this, 'check_token'],
            'args' => [],
        ]);
    }

    private const RATE_LIMIT_KEY = 'fp_fpmail_brevo_webhook_rl';

    private const RATE_LIMIT_MAX = 60;

    private const RATE_LIMIT_WINDOW = 60;

    /**
     * Verifica il token webhook (query ?token= o Authorization Bearer) e rate limiting.
     *
     * @param WP_REST_Request $request Request.
     * @return bool
     */
    public function check_token(WP_REST_Request $request): bool
    {
        if (get_option('fp_fpmail_brevo_log_enabled', '0') !== '1') {
            return false;
        }

        $token = get_option('fp_fpmail_brevo_webhook_token', '');
        if ($token === '') {
            return false;
        }

        $provided = $request->get_param('token')
            ?? $this->get_bearer_token($request);

        if (!is_string($provided) || !hash_equals($token, $provided)) {
            return false;
        }

        return $this->check_rate_limit();
    }

    /**
     * Rate limiting basato su transient (max richieste per minuto per IP).
     *
     * @return bool True se entro il limite.
     */
    private function check_rate_limit(): bool
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = self::RATE_LIMIT_KEY . '_' . md5($ip);
        $data = get_transient($key);
        $now = time();

        if ($data === false || !is_array($data)) {
            $data = ['c' => 0, 't' => $now];
        }
        if ($now - $data['t'] > self::RATE_LIMIT_WINDOW) {
            $data = ['c' => 0, 't' => $now];
        }

        $data['c']++;
        if ($data['c'] > self::RATE_LIMIT_MAX) {
            return false;
        }

        $ttl = max(1, self::RATE_LIMIT_WINDOW - ($now - $data['t']));
        set_transient($key, $data, $ttl);

        return true;
    }

    /**
     * Estrae Bearer token dall'header.
     *
     * @param WP_REST_Request $request Request.
     * @return string|null
     */
    private function get_bearer_token(WP_REST_Request $request): ?string
    {
        $auth = $request->get_header('Authorization');
        if (!$auth || !preg_match('/^Bearer\s+(.+)$/i', trim($auth), $m)) {
            return null;
        }
        return $m[1];
    }

    /**
     * Gestisce il payload webhook Brevo.
     *
     * @param WP_REST_Request $request Request con body JSON.
     * @return WP_REST_Response
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $raw = $request->get_body();
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $body = is_array($decoded) ? $decoded : [];
        }

        $event = $body['event'] ?? '';
        $email = isset($body['email']) ? sanitize_email((string) $body['email']) : '';

        if ($event === '' || $email === '') {
            return new WP_REST_Response(['error' => 'Missing event or email'], 400);
        }

        $status = $this->map_event_to_status($event);
        if ($status === null) {
            return new WP_REST_Response(['ok' => true, 'ignored' => $event], 200);
        }

        $subject = isset($body['subject'])
            ? str_replace(["\r", "\n"], '', sanitize_text_field((string) $body['subject']))
            : '';
        $messageId = isset($body['message-id']) ? sanitize_text_field((string) $body['message-id']) : '';
        $date = $body['date'] ?? '';
        $reason = $body['reason'] ?? '';
        $mirrorLink = $body['mirror_link'] ?? '';
        $tags = isset($body['tags']) && is_array($body['tags']) ? implode(', ', $body['tags']) : '';

        $errorMessage = $status === 'failed' ? ($reason ?: ucfirst((string) $event)) : '';

        global $wpdb;
        $table = $wpdb->prefix . 'fp_fpmail_logs';

        if (in_array($event, self::DELIVERED_EVENTS, true) && $messageId !== '') {
            $updated = $wpdb->update(
                $table,
                [
                    'status' => 'sent',
                    'brevo_event' => sanitize_text_field($event),
                ],
                [
                    'brevo_message_id' => $messageId,
                    'source' => 'brevo',
                ],
                ['%s', '%s'],
                ['%s', '%s']
            );
            if ($updated > 0) {
                return new WP_REST_Response(['ok' => true, 'updated' => $updated], 200);
            }
        }

        $createdAt = $date ? $this->parse_brevo_date($date) : current_time('mysql');

        $wpdb->insert(
            $table,
            [
                'to_addresses' => $email,
                'from_email' => '',
                'subject' => mb_substr($subject, 0, 500),
                'message_preview' => '',
                'headers' => $mirrorLink ? 'mirror_link: ' . esc_url_raw($mirrorLink) : '',
                'attachments_count' => 0,
                'status' => $status,
                'error_message' => sanitize_textarea_field($errorMessage),
                'source' => 'brevo',
                'brevo_event' => sanitize_text_field($event),
                'brevo_message_id' => mb_substr($messageId, 0, 255),
                'created_at' => $createdAt,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * Mappa evento Brevo a status log.
     *
     * @param string $event Nome evento Brevo.
     * @return string|null 'sent', 'failed' o null se da ignorare.
     */
    private function map_event_to_status(string $event): ?string
    {
        if (in_array($event, self::SENT_EVENTS, true)) {
            return 'sent';
        }
        if (in_array($event, self::FAILED_EVENTS, true)) {
            return 'failed';
        }
        if (in_array($event, self::DELIVERED_EVENTS, true)) {
            return 'sent';
        }
        return null;
    }

    /**
     * Converte data Brevo (Y-m-d H:i:s) in formato MySQL.
     *
     * @param string $date Data Brevo.
     * @return string
     */
    private function parse_brevo_date(string $date): string
    {
        $ts = strtotime($date);
        return $ts !== false ? gmdate('Y-m-d H:i:s', $ts) : current_time('mysql');
    }
}
