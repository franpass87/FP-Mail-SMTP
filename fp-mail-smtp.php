<?php

declare(strict_types=1);

/**
 * Plugin Name:       FP Mail SMTP
 * Plugin URI:        https://github.com/franpass87/FP-Mail-SMTP
 * Description:       Configurazione SMTP per WordPress e log completo di tutte le email in uscita. Compatibile con tutti i plugin FP.
 * Version:           1.2.3
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

define('FP_FPMAIL_VERSION', '1.2.3');
define('FP_FPMAIL_FILE', __FILE__);
define('FP_FPMAIL_DIR', plugin_dir_path(__FILE__));
define('FP_FPMAIL_URL', plugin_dir_url(__FILE__));
define('FP_FPMAIL_BASENAME', plugin_basename(__FILE__));

if (file_exists(FP_FPMAIL_DIR . 'vendor/autoload.php')) {
    require_once FP_FPMAIL_DIR . 'vendor/autoload.php';
}

if (! function_exists('fp_fpmail_brand_html')) {
    /**
     * Applica il wrapper HTML branding FP (stesso layout di FP Experiences).
     * Con FP Mail SMTP disattivato o branding disattivato nelle impostazioni, restituisce $html invariato.
     *
     * @since 1.2.0
     *
     * @param array<string, mixed> $args Passate al filtro `fp_fpmail_brand_html` (es. `skip_branding` => true).
     */
    function fp_fpmail_brand_html(string $html, array $args = []): string
    {
        return apply_filters('fp_fpmail_brand_html', $html, $args);
    }
}

add_action('plugins_loaded', static function (): void {
    \FP\Fpmail\Core\Plugin::instance()->init();
});

register_activation_hook(__FILE__, static function (): void {
    \FP\Fpmail\Core\Plugin::instance()->maybeCreateTable();
    \FP\Fpmail\Core\Plugin::instance()->scheduleCron();
});
