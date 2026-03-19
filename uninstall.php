<?php
/**
 * Uninstall FP Mail SMTP
 *
 * Pulizia DB e opzioni alla disinstallazione del plugin.
 *
 * @package FP\Fpmail
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

// Rimuovi tabella log
$table = $wpdb->prefix . 'fp_fpmail_logs';
$wpdb->query("DROP TABLE IF EXISTS {$table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Rimuovi opzioni
$options = [
    'fp_fpmail_smtp_host',
    'fp_fpmail_smtp_port',
    'fp_fpmail_smtp_encryption',
    'fp_fpmail_smtp_user',
    'fp_fpmail_smtp_pass',
    'fp_fpmail_from_email',
    'fp_fpmail_from_name',
    'fp_fpmail_log_retention_days',
    'fp_fpmail_log_enabled',
];

foreach ($options as $option) {
    delete_option($option);
}

// Rimuovi cron
wp_clear_scheduled_hook('fp_fpmail_cleanup_logs');
