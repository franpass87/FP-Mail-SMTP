<?php
/**
 * Controller REST per webhook Brevo (solo se ingest method = webhook).
 *
 * @package FP\Fpmail\Api
 */

declare(strict_types=1);

namespace FP\Fpmail\Api;

use FP\Fpmail\Brevo\BrevoLogIngestor;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handler opzionale per i webhook Brevo transactional.
 */
final class BrevoWebhookController
{
    private const NAMESPACE = 'fp/fpmail/v1';

    private const RATE_LIMIT_KEY = 'fp_fpmail_brevo_webhook_rl';

    private const RATE_LIMIT_MAX = 60;

    private const RATE_LIMIT_WINDOW = 60;

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

    /**
     * Verifica token, rate limit e modalità webhook.
     */
    public function check_token(WP_REST_Request $request): bool
    {
        if (get_option('fp_fpmail_brevo_log_enabled', '0') !== '1') {
            return false;
        }

        if (get_option('fp_fpmail_brevo_ingest_method', 'api') !== 'webhook') {
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
     */
    public function handle_webhook(WP_REST_Request $request): WP_REST_Response
    {
        $body = $request->get_json_params();
        if (!is_array($body)) {
            $raw = $request->get_body();
            $decoded = is_string($raw) ? json_decode($raw, true) : null;
            $body = is_array($decoded) ? $decoded : [];
        }

        $result = BrevoLogIngestor::ingest_webhook_body($body);
        if (!empty($result['error'])) {
            return new WP_REST_Response(['error' => $result['error']], 400);
        }
        if (isset($result['ignored'])) {
            return new WP_REST_Response(['ok' => true, 'ignored' => $result['ignored']], 200);
        }

        return new WP_REST_Response(['ok' => true], 200);
    }
}
