<?php
/**
 * Bootstrap del plugin FP Mail SMTP.
 *
 * @package FP\Fpmail\Core
 */

declare(strict_types=1);

namespace FP\Fpmail\Core;

use FP\Fpmail\Admin\LogPage;
use FP\Fpmail\Admin\SettingsPage;
use FP\Fpmail\Api\BrevoWebhookController;
use FP\Fpmail\Branding\BrandingService;
use FP\Fpmail\Mail\SmtpConfigurator;

/**
 * Singleton principale del plugin.
 *
 * Gestisce bootstrap, creazione tabella, registrazione hook e cron retention.
 */
final class Plugin
{
    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $options = [];

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Inizializza il plugin.
     */
    public function init(): void
    {
        $this->options = $this->getDefaultOptions();

        load_plugin_textdomain('fp-fpmail', false, dirname(plugin_basename(FP_FPMAIL_FILE)) . '/languages');

        add_action('init', [$this, 'maybeCreateTable']);
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('admin_menu', [$this, 'registerAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('wp_loaded', [$this, 'registerHooks'], 5);

        // Cron cleanup logs
        add_action('fp_fpmail_cleanup_logs', [$this, 'cleanupOldLogs']);

        // Deactivation: pulizia cron
        register_deactivation_hook(FP_FPMAIL_FILE, [$this, 'onDeactivate']);
    }

    /**
     * Crea la tabella log e applica migrazioni.
     */
    public function maybeCreateTable(): void
    {
        $option = 'fp_fpmail_db_version';
        $current = get_option($option, '0');

        global $wpdb;
        $table = $wpdb->prefix . 'fp_fpmail_logs';

        if (version_compare($current, '1.0', '<')) {
            $charset = $wpdb->get_charset_collate();
            $sql = "CREATE TABLE {$table} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                to_addresses TEXT NOT NULL,
                from_email VARCHAR(255) NOT NULL DEFAULT '',
                subject VARCHAR(500) NOT NULL DEFAULT '',
                message_preview TEXT NOT NULL DEFAULT '',
                headers TEXT NOT NULL DEFAULT '',
                attachments_count INT UNSIGNED NOT NULL DEFAULT 0,
                status VARCHAR(20) NOT NULL DEFAULT 'sent',
                error_message TEXT NOT NULL DEFAULT '',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                source VARCHAR(20) NOT NULL DEFAULT 'wp_mail',
                brevo_event VARCHAR(50) NOT NULL DEFAULT '',
                brevo_message_id VARCHAR(255) NOT NULL DEFAULT '',
                PRIMARY KEY (id),
                KEY status (status),
                KEY created_at (created_at),
                KEY source (source)
            ) {$charset};";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            dbDelta($sql);
            update_option($option, '1.0');
        }

        if (version_compare($current, '1.1', '<')) {
            $cols = $wpdb->get_col("SHOW COLUMNS FROM {$table} LIKE 'source'");
            if (empty($cols)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN source VARCHAR(20) NOT NULL DEFAULT 'wp_mail' AFTER created_at");
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN brevo_event VARCHAR(50) NOT NULL DEFAULT '' AFTER source");
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN brevo_message_id VARCHAR(255) NOT NULL DEFAULT '' AFTER brevo_event");
                $wpdb->query("ALTER TABLE {$table} ADD KEY source (source)");
            }
            update_option($option, '1.1');
        }
    }

    /**
     * Registra le voci di menu admin.
     */
    public function registerAdminMenu(): void
    {
        add_menu_page(
            __('FP Mail SMTP', 'fp-fpmail'),
            __('FP Mail SMTP', 'fp-fpmail'),
            'manage_options',
            'fp-fpmail',
            [$this, 'renderSettingsPage'],
            'dashicons-email-alt',
            '56.5'
        );

        add_submenu_page(
            'fp-fpmail',
            __('Impostazioni', 'fp-fpmail'),
            __('Impostazioni', 'fp-fpmail'),
            'manage_options',
            'fp-fpmail',
            [$this, 'renderSettingsPage']
        );

        add_submenu_page(
            'fp-fpmail',
            __('Log Email', 'fp-fpmail'),
            __('Log Email', 'fp-fpmail'),
            'manage_options',
            'fp-fpmail-logs',
            [$this, 'renderLogPage']
        );
    }

    /**
     * Callback per registrare le impostazioni.
     */
    public function registerSettings(): void
    {
        (new SettingsPage())->register();
    }

    /**
     * Renderizza la pagina impostazioni.
     */
    public function renderSettingsPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Non hai i permessi per accedere a questa pagina.', 'fp-fpmail'), '', ['response' => 403]);
        }
        (new SettingsPage())->render();
    }

    /**
     * Renderizza la pagina log.
     */
    public function renderLogPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Non hai i permessi per accedere a questa pagina.', 'fp-fpmail'), '', ['response' => 403]);
        }
        (new LogPage())->render();
    }

    /**
     * Registra le route REST.
     */
    public function registerRestRoutes(): void
    {
        (new BrevoWebhookController())->register_routes();
    }

    /**
     * Registra gli hook per SMTP e logging.
     */
    public function registerHooks(): void
    {
        (new BrandingService())->register();
        (new SmtpConfigurator())->register();
        (new MailLogger())->register();
    }

    /**
     * Pulizia log vecchi (cron).
     */
    public function cleanupOldLogs(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_fpmail_logs';
        $days = (int) get_option('fp_fpmail_log_retention_days', 30);
        $days = max(1, min(365, $days));
        $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table} WHERE created_at < %s",
                $cutoff
            )
        );
    }

    /**
     * Schedula il cron alla prima attivazione.
     */
    public function scheduleCron(): void
    {
        if (!wp_next_scheduled('fp_fpmail_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'fp_fpmail_cleanup_logs');
        }
    }

    /**
     * Callback alla disattivazione.
     */
    public function onDeactivate(): void
    {
        wp_clear_scheduled_hook('fp_fpmail_cleanup_logs');
    }

    /**
     * Opzioni di default.
     *
     * @return array<string, mixed>
     */
    private function getDefaultOptions(): array
    {
        return [
            'fp_fpmail_smtp_host' => '',
            'fp_fpmail_smtp_port' => 587,
            'fp_fpmail_smtp_encryption' => 'tls',
            'fp_fpmail_smtp_user' => '',
            'fp_fpmail_smtp_pass' => '',
            'fp_fpmail_from_email' => get_option('admin_email', ''),
            'fp_fpmail_from_name' => get_bloginfo('name', 'raw'),
            'fp_fpmail_log_retention_days' => 30,
            'fp_fpmail_log_enabled' => '1',
        ];
    }

    private function __construct()
    {
    }
}
