<?php

declare(strict_types=1);

/**
 * Plugin Name:       FP Mail SMTP
 * Plugin URI:        https://github.com/franpass87/FP-Mail-SMTP
 * Description:       Configurazione SMTP per WordPress e log completo di tutte le email in uscita. Compatibile con tutti i plugin FP.
 * Version:           1.1.4
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Francesco Passeri
 * Author URI:        https://francescopasseri.com
 * License:           Proprietary
 * Text Domain:       fp-fpmail
 * GitHub Plugin URI: franpass87/FP-Mail-SMTP
 * Primary Branch:    main
 */

defined('ABSPATH') || exit;

define('FP_FPMAIL_VERSION', '1.1.4');
define('FP_FPMAIL_FILE', __FILE__);
define('FP_FPMAIL_DIR', plugin_dir_path(__FILE__));
define('FP_FPMAIL_URL', plugin_dir_url(__FILE__));
define('FP_FPMAIL_BASENAME', plugin_basename(__FILE__));

if (file_exists(FP_FPMAIL_DIR . 'vendor/autoload.php')) {
    require_once FP_FPMAIL_DIR . 'vendor/autoload.php';
}

add_action('plugins_loaded', static function (): void {
    \FP\Fpmail\Core\Plugin::instance()->init();
});

register_activation_hook(__FILE__, static function (): void {
    \FP\Fpmail\Core\Plugin::instance()->maybeCreateTable();
    \FP\Fpmail\Core\Plugin::instance()->scheduleCron();
});
