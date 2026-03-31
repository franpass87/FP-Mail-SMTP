<?php
/**
 * Serve l’HTML completo di un log per anteprima in iframe (admin).
 *
 * @package FP\Fpmail\Admin
 */

declare(strict_types=1);

namespace FP\Fpmail\Admin;

/**
 * Endpoint `admin-ajax.php?action=fp_fpmail_log_html` (solo utenti con `manage_options`).
 */
final class LogPreviewHandler
{
    private const NONCE_ACTION = 'fp_fpmail_log_html';

    /**
     * Registra l’handler AJAX.
     */
    public function register(): void
    {
        add_action('wp_ajax_fp_fpmail_log_html', [$this, 'outputHtml']);
    }

    /**
     * Invia il corpo HTML del log con header di sicurezza (CSP senza script).
     */
    public function outputHtml(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permesso negato.', 'fp-fpmail'), '', ['response' => 403]);
        }

        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $id = isset($_GET['id']) ? absint($_GET['id']) : 0;
        if ($id < 1) {
            wp_die(esc_html__('ID non valido.', 'fp-fpmail'), '', ['response' => 400]);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'fp_fpmail_logs';
        $body = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT message_body FROM {$table} WHERE id = %d",
                $id
            )
        );

        if (! is_string($body)) {
            $body = '';
        }

        nocache_headers();
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Robots-Tag: noindex, nofollow');
        // Blocca esecuzione script nel contenuto email archiviato; consente stili inline tipici delle newsletter.
        header(
            "Content-Security-Policy: default-src 'none'; "
            . "img-src * data: blob: https: http:; "
            . "style-src 'unsafe-inline'; "
            . "font-src * data: https: http:; "
            . "base-uri 'none'; form-action 'none'; frame-ancestors 'self'"
        );

        if ('' === trim($body)) {
            $this->emitPlaceholder(
                esc_html__(
                    "Anteprima non disponibile: corpo non salvato (email registrata prima dell'aggiornamento o invio senza HTML).",
                    'fp-fpmail'
                )
            );
            exit;
        }

        if (preg_match('/<html[\s>]/i', $body)) {
            echo $body;
            exit;
        }

        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head><body>';
        echo $body;
        echo '</body></html>';
        exit;
    }

    /**
     * Pagina minimale quando manca il corpo.
     */
    private function emitPlaceholder(string $message): void
    {
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>body{font-family:system-ui,sans-serif;padding:1.5rem;color:#334155;background:#f8fafc;}</style></head><body><p>';
        echo $message;
        echo '</p></body></html>';
    }

    /**
     * Azione nonce condivisa con il template dettaglio log.
     */
    public static function nonceAction(): string
    {
        return self::NONCE_ACTION;
    }
}
