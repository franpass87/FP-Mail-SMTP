<?php
/**
 * Pagina impostazioni SMTP.
 *
 * Form configurazione SMTP, from, log e pulsante test email.
 *
 * @package FP\Fpmail\Admin
 */

declare(strict_types=1);

namespace FP\Fpmail\Admin;

/**
 * Pagina admin per le impostazioni FP Mail SMTP.
 */
final class SettingsPage
{
    /**
     * Registra impostazioni e gestisce salvataggio / AJAX test.
     */
    public function register(): void
    {
        add_action('admin_post_fp_fpmail_save_settings', [$this, 'handleSave']);
        add_action('wp_ajax_fp_fpmail_send_test', [$this, 'handleTestEmail']);
        add_action('wp_ajax_fp_fpmail_regenerate_brevo_token', [$this, 'handleRegenerateBrevoToken']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    /**
     * Carica CSS/JS sulle pagine del plugin.
     *
     * @param string $hook Hook della pagina corrente.
     */
    public function enqueueAssets(string $hook): void
    {
        if (strpos($hook, 'fp-fpmail') === false) {
            return;
        }
        wp_enqueue_style(
            'fp-fpmail-admin',
            FP_FPMAIL_URL . 'assets/css/admin.css',
            [],
            FP_FPMAIL_VERSION
        );
    }

    /**
     * Gestisce il salvataggio del form.
     */
    public function handleSave(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Accesso negato.', 'fp-fpmail'), '', ['response' => 403]);
        }
        check_admin_referer('fp_fpmail_save_settings', 'fp_fpmail_nonce');

        $fields = [
            'fp_fpmail_smtp_host' => 'sanitize_text_field',
            'fp_fpmail_smtp_port' => 'absint',
            'fp_fpmail_smtp_encryption' => fn ($v) => in_array($v, ['none', 'ssl', 'tls'], true) ? $v : 'tls',
            'fp_fpmail_smtp_user' => 'sanitize_text_field',
            'fp_fpmail_from_email' => 'sanitize_email',
            'fp_fpmail_from_name' => 'sanitize_text_field',
            'fp_fpmail_log_retention_days' => fn ($v) => max(1, min(365, absint($v))),
            'fp_fpmail_log_enabled' => fn ($v) => $v === '1' ? '1' : '0',
            'fp_fpmail_brevo_log_enabled' => fn ($v) => $v === '1' ? '1' : '0',
        ];

        foreach ($fields as $key => $sanitizer) {
            $raw = isset($_POST[$key]) ? wp_unslash($_POST[$key]) : '';
            $value = is_callable($sanitizer) ? $sanitizer($raw) : $sanitizer($raw);
            update_option($key, $value);
        }

        // Password: cifrata in base64
        if (isset($_POST['fp_fpmail_smtp_pass']) && $_POST['fp_fpmail_smtp_pass'] !== '') {
            $pass = sanitize_text_field(wp_unslash($_POST['fp_fpmail_smtp_pass']));
            update_option('fp_fpmail_smtp_pass', base64_encode($pass));
        }

        // Brevo: genera token se abilitato e vuoto
        if (get_option('fp_fpmail_brevo_log_enabled', '0') === '1') {
            $token = get_option('fp_fpmail_brevo_webhook_token', '');
            if ($token === '') {
                update_option('fp_fpmail_brevo_webhook_token', wp_generate_password(32, true, true));
            }
        }

        wp_safe_redirect(
            add_query_arg(['page' => 'fp-fpmail', 'saved' => '1'], admin_url('admin.php'))
        );
        exit;
    }

    /**
     * AJAX: invia email di test.
     */
    public function handleTestEmail(): void
    {
        check_ajax_referer('fp_fpmail_test_email', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Accesso negato.', 'fp-fpmail')]);
        }

        $to = isset($_POST['to']) ? sanitize_email(wp_unslash($_POST['to'])) : '';
        if ($to === '' || !is_email($to)) {
            wp_send_json_error(['message' => __('Indirizzo email non valido.', 'fp-fpmail')]);
        }

        $subject = str_replace(["\r", "\n"], '', sprintf(
            /* translators: %s: site name */
            __('[%s] Email di test FP Mail SMTP', 'fp-fpmail'),
            get_bloginfo('name')
        ));
        $body = sprintf(
            /* translators: 1: site url, 2: current datetime */
            __("Questa è un'email di test da FP Mail SMTP.\n\nSito: %1\$s\nData/Ora: %2\$s", 'fp-fpmail'),
            home_url(),
            wp_date('Y-m-d H:i:s')
        );

        $sent = wp_mail($to, $subject, $body);

        if ($sent) {
            wp_send_json_success(['message' => __('Email di test inviata con successo.', 'fp-fpmail')]);
        }

        wp_send_json_error(['message' => __('Invio fallito. Controlla i log e la configurazione SMTP.', 'fp-fpmail')]);
    }

    /**
     * AJAX: rigenera token webhook Brevo.
     */
    public function handleRegenerateBrevoToken(): void
    {
        check_ajax_referer('fp_fpmail_regenerate_brevo_token', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Accesso negato.', 'fp-fpmail')]);
        }
        $token = wp_generate_password(32, true, true);
        update_option('fp_fpmail_brevo_webhook_token', $token);
        $url = rest_url('fp/fpmail/v1/brevo-webhook') . '?token=' . rawurlencode($token);
        wp_send_json_success(['token' => $token, 'url' => $url]);
    }

    /**
     * Renderizza la pagina impostazioni.
     */
    public function render(): void
    {
        $saved = isset($_GET['saved']) && $_GET['saved'] === '1';
        $host = get_option('fp_fpmail_smtp_host', '');
        $smtpConfigured = $host !== '';
        $adminEmail = get_option('admin_email', '');
        ?>
        <div class="wrap fpmail-admin-page">
            <div class="fpmail-page-header">
                <div class="fpmail-page-header-content">
                    <h1><span class="dashicons dashicons-email-alt"></span> <?php esc_html_e('FP Mail SMTP', 'fp-fpmail'); ?></h1>
                    <p><?php esc_html_e('Configura SMTP e visualizza il log di tutte le email in uscita.', 'fp-fpmail'); ?></p>
                </div>
                <span class="fpmail-page-header-badge">v<?php echo esc_html(FP_FPMAIL_VERSION); ?></span>
            </div>

            <?php if ($saved) : ?>
                <div class="fpmail-alert fpmail-alert-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <?php esc_html_e('Impostazioni salvate.', 'fp-fpmail'); ?>
                </div>
            <?php endif; ?>

            <div class="fpmail-status-bar">
                <span class="fpmail-status-pill <?php echo $smtpConfigured ? 'is-active' : 'is-missing'; ?>">
                    <span class="dot"></span>
                    <?php echo $smtpConfigured ? esc_html__('SMTP configurato', 'fp-fpmail') : esc_html__('SMTP non configurato', 'fp-fpmail'); ?>
                </span>
                <span class="fpmail-status-pill is-active">
                    <span class="dot"></span>
                    <?php esc_html_e('Log email attivo', 'fp-fpmail'); ?>
                </span>
            </div>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="fpmail-settings-form">
                <input type="hidden" name="action" value="fp_fpmail_save_settings">
                <?php wp_nonce_field('fp_fpmail_save_settings', 'fp_fpmail_nonce'); ?>

                <div class="fpmail-card">
                    <div class="fpmail-card-header">
                        <div class="fpmail-card-header-left">
                            <span class="dashicons dashicons-admin-generic"></span>
                            <h2><?php esc_html_e('Configurazione SMTP', 'fp-fpmail'); ?></h2>
                        </div>
                        <span class="fpmail-badge <?php echo $smtpConfigured ? 'fpmail-badge-success' : 'fpmail-badge-neutral'; ?>">
                            <?php echo $smtpConfigured ? '&#10003; ' . esc_html__('Configurato', 'fp-fpmail') : esc_html__('Non impostato', 'fp-fpmail'); ?>
                        </span>
                    </div>
                    <div class="fpmail-card-body">
                        <p class="description"><?php esc_html_e('Inserisci i dati del server SMTP. Se lasciato vuoto, WordPress userà la funzione mail() PHP.', 'fp-fpmail'); ?></p>
                        <div class="fpmail-fields-grid">
                            <div class="fpmail-field">
                                <label for="fp_fpmail_smtp_host"><?php esc_html_e('Host SMTP', 'fp-fpmail'); ?></label>
                                <input type="text" id="fp_fpmail_smtp_host" name="fp_fpmail_smtp_host"
                                       value="<?php echo esc_attr($host); ?>"
                                       placeholder="smtp.example.com" class="regular-text is-monospace">
                            </div>
                            <div class="fpmail-field">
                                <label for="fp_fpmail_smtp_port"><?php esc_html_e('Porta', 'fp-fpmail'); ?></label>
                                <input type="number" id="fp_fpmail_smtp_port" name="fp_fpmail_smtp_port"
                                       value="<?php echo esc_attr((string) get_option('fp_fpmail_smtp_port', 587)); ?>"
                                       min="1" max="65535" class="small-text">
                                <span class="fpmail-hint"><?php esc_html_e('25, 465 (SSL), 587 (TLS)', 'fp-fpmail'); ?></span>
                            </div>
                            <div class="fpmail-field">
                                <label for="fp_fpmail_smtp_encryption"><?php esc_html_e('Crittografia', 'fp-fpmail'); ?></label>
                                <select id="fp_fpmail_smtp_encryption" name="fp_fpmail_smtp_encryption">
                                    <option value="none" <?php selected(get_option('fp_fpmail_smtp_encryption', 'tls'), 'none'); ?>><?php esc_html_e('Nessuna', 'fp-fpmail'); ?></option>
                                    <option value="ssl" <?php selected(get_option('fp_fpmail_smtp_encryption', 'tls'), 'ssl'); ?>>SSL</option>
                                    <option value="tls" <?php selected(get_option('fp_fpmail_smtp_encryption', 'tls'), 'tls'); ?>>TLS</option>
                                </select>
                            </div>
                            <div class="fpmail-field">
                                <label for="fp_fpmail_smtp_user"><?php esc_html_e('Username SMTP', 'fp-fpmail'); ?></label>
                                <input type="text" id="fp_fpmail_smtp_user" name="fp_fpmail_smtp_user"
                                       value="<?php echo esc_attr(get_option('fp_fpmail_smtp_user', '')); ?>"
                                       class="regular-text" autocomplete="off">
                            </div>
                            <div class="fpmail-field">
                                <label for="fp_fpmail_smtp_pass"><?php esc_html_e('Password SMTP', 'fp-fpmail'); ?></label>
                                <input type="password" id="fp_fpmail_smtp_pass" name="fp_fpmail_smtp_pass"
                                       value="" class="regular-text" autocomplete="new-password"
                                       placeholder="<?php esc_attr_e('Lascia vuoto per non modificare', 'fp-fpmail'); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fpmail-card">
                    <div class="fpmail-card-header">
                        <div class="fpmail-card-header-left">
                            <span class="dashicons dashicons-email"></span>
                            <h2><?php esc_html_e('Mittente predefinito', 'fp-fpmail'); ?></h2>
                        </div>
                    </div>
                    <div class="fpmail-card-body">
                        <div class="fpmail-fields-grid">
                            <div class="fpmail-field">
                                <label for="fp_fpmail_from_email"><?php esc_html_e('Email mittente', 'fp-fpmail'); ?></label>
                                <input type="email" id="fp_fpmail_from_email" name="fp_fpmail_from_email"
                                       value="<?php echo esc_attr(get_option('fp_fpmail_from_email', $adminEmail)); ?>"
                                       class="regular-text">
                            </div>
                            <div class="fpmail-field">
                                <label for="fp_fpmail_from_name"><?php esc_html_e('Nome mittente', 'fp-fpmail'); ?></label>
                                <input type="text" id="fp_fpmail_from_name" name="fp_fpmail_from_name"
                                       value="<?php echo esc_attr(get_option('fp_fpmail_from_name', get_bloginfo('name', 'raw'))); ?>"
                                       class="regular-text">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="fpmail-card">
                    <div class="fpmail-card-header">
                        <div class="fpmail-card-header-left">
                            <span class="dashicons dashicons-list-view"></span>
                            <h2><?php esc_html_e('Log email', 'fp-fpmail'); ?></h2>
                        </div>
                    </div>
                    <div class="fpmail-card-body">
                        <div class="fpmail-toggle-row">
                            <div class="fpmail-toggle-info">
                                <strong><?php esc_html_e('Abilita log', 'fp-fpmail'); ?></strong>
                                <span><?php esc_html_e('Registra tutte le email inviate e fallite', 'fp-fpmail'); ?></span>
                            </div>
                            <label class="fpmail-toggle">
                                <input type="checkbox" name="fp_fpmail_log_enabled" value="1"
                                       <?php checked(get_option('fp_fpmail_log_enabled', '1'), '1'); ?>>
                                <span class="fpmail-toggle-slider"></span>
                            </label>
                        </div>
                        <div class="fpmail-field fpmail-field-spacing">
                            <label for="fp_fpmail_log_retention_days"><?php esc_html_e('Retention log (giorni)', 'fp-fpmail'); ?></label>
                            <input type="number" id="fp_fpmail_log_retention_days" name="fp_fpmail_log_retention_days"
                                   value="<?php echo esc_attr((string) get_option('fp_fpmail_log_retention_days', 30)); ?>"
                                   min="1" max="365" class="small-text">
                            <span class="fpmail-hint"><?php esc_html_e('1–365, i log più vecchi vengono eliminati automaticamente', 'fp-fpmail'); ?></span>
                        </div>
                    </div>
                </div>

                <?php
                $brevoEnabled = get_option('fp_fpmail_brevo_log_enabled', '0') === '1';
                $brevoToken = get_option('fp_fpmail_brevo_webhook_token', '');
                $brevoWebhookUrl = $brevoToken !== '' ? rest_url('fp/fpmail/v1/brevo-webhook') . '?token=' . rawurlencode($brevoToken) : '';
                ?>
                <div class="fpmail-card">
                    <div class="fpmail-card-header">
                        <div class="fpmail-card-header-left">
                            <span class="dashicons dashicons-share"></span>
                            <h2><?php esc_html_e('Integrazione Brevo', 'fp-fpmail'); ?></h2>
                        </div>
                        <span class="fpmail-badge <?php echo $brevoEnabled ? 'fpmail-badge-success' : 'fpmail-badge-neutral'; ?>">
                            <?php echo $brevoEnabled ? '&#10003; ' . esc_html__('Attiva', 'fp-fpmail') : esc_html__('Disattiva', 'fp-fpmail'); ?>
                        </span>
                    </div>
                    <div class="fpmail-card-body">
                        <p class="description"><?php esc_html_e('Mostra nel log anche le email inviate tramite Brevo (API Transactional), incluse quelle da FP-Restaurant-Reservations, FP-Experiences, FP-Forms quando usano Brevo come canale.', 'fp-fpmail'); ?></p>
                        <div class="fpmail-toggle-row">
                            <div class="fpmail-toggle-info">
                                <strong><?php esc_html_e('Abilita log eventi Brevo', 'fp-fpmail'); ?></strong>
                                <span><?php esc_html_e('Ricevi eventi via webhook e visualizzali nel log unificato', 'fp-fpmail'); ?></span>
                            </div>
                            <label class="fpmail-toggle">
                                <input type="checkbox" name="fp_fpmail_brevo_log_enabled" value="1"
                                       <?php checked($brevoEnabled, true); ?>>
                                <span class="fpmail-toggle-slider"></span>
                            </label>
                        </div>
                        <?php if ($brevoEnabled && $brevoWebhookUrl !== '') : ?>
                            <div class="fpmail-field fpmail-field-spacing">
                                <label><?php esc_html_e('URL Webhook da configurare in Brevo', 'fp-fpmail'); ?></label>
                                <div class="fpmail-brevo-url-row">
                                    <input type="text" id="fpmail-brevo-url" value="<?php echo esc_attr($brevoWebhookUrl); ?>"
                                           readonly class="large-text is-monospace">
                                    <button type="button" id="fpmail-brevo-copy" class="fpmail-btn fpmail-btn-secondary">
                                        <?php esc_html_e('Copia', 'fp-fpmail'); ?>
                                    </button>
                                    <button type="button" id="fpmail-brevo-regenerate" class="fpmail-btn fpmail-btn-secondary">
                                        <?php esc_html_e('Rigenera token', 'fp-fpmail'); ?>
                                    </button>
                                </div>
                                <p class="fpmail-hint"><?php esc_html_e('Brevo > Transactional > Webhooks: aggiungi questo URL e seleziona gli eventi Sent, Delivered, Hard bounce, Soft bounce, Blocked, Invalid, Error.', 'fp-fpmail'); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="fpmail-card">
                    <div class="fpmail-card-body">
                        <button type="submit" class="fpmail-btn fpmail-btn-primary">
                            <span class="dashicons dashicons-saved"></span>
                            <?php esc_html_e('Salva impostazioni', 'fp-fpmail'); ?>
                        </button>
                    </div>
                </div>
            </form>

            <div class="fpmail-card">
                <div class="fpmail-card-header">
                    <div class="fpmail-card-header-left">
                        <span class="dashicons dashicons-testimonial"></span>
                        <h2><?php esc_html_e('Email di test', 'fp-fpmail'); ?></h2>
                    </div>
                </div>
                <div class="fpmail-card-body">
                    <p class="description"><?php esc_html_e('Invia un\'email di test per verificare la configurazione.', 'fp-fpmail'); ?></p>
                    <div class="fpmail-test-email-row">
                        <input type="email" id="fpmail-test-to" value="<?php echo esc_attr($adminEmail); ?>"
                               placeholder="<?php esc_attr_e('Destinatario', 'fp-fpmail'); ?>" class="regular-text">
                        <button type="button" id="fpmail-test-send" class="fpmail-btn fpmail-btn-secondary">
                            <span class="dashicons dashicons-email-alt"></span>
                            <?php esc_html_e('Invia test', 'fp-fpmail'); ?>
                        </button>
                    </div>
                    <div id="fpmail-test-result" class="fpmail-alert fpmail-test-result"></div>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var btn = document.getElementById('fpmail-test-send');
            var toInput = document.getElementById('fpmail-test-to');
            var result = document.getElementById('fpmail-test-result');
            if (!btn || !toInput || !result) return;
            btn.addEventListener('click', function() {
                btn.disabled = true;
                result.classList.remove('is-visible');
                var formData = new FormData();
                formData.append('action', 'fp_fpmail_send_test');
                formData.append('nonce', '<?php echo esc_js(wp_create_nonce('fp_fpmail_test_email')); ?>');
                formData.append('to', toInput.value);
                fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    result.classList.add('is-visible');
                    result.className = 'fpmail-alert fpmail-test-result ' + (data.success ? 'fpmail-alert-success' : 'fpmail-alert-danger');
                    result.innerHTML = (data.success ? '<span class="dashicons dashicons-yes-alt"></span> ' : '<span class="dashicons dashicons-warning"></span> ') +
                        (data.data && data.data.message ? data.data.message : (data.data && data.data[0] ? data.data[0].message : ''));
                })
                .catch(function() {
                    result.classList.add('is-visible');
                    result.className = 'fpmail-alert fpmail-test-result fpmail-alert-danger';
                    result.innerHTML = '<span class="dashicons dashicons-warning"></span> <?php echo esc_js(__('Errore di connessione.', 'fp-fpmail')); ?>';
                })
                .finally(function() { btn.disabled = false; });
            });

            var copyBtn = document.getElementById('fpmail-brevo-copy');
            var urlInput = document.getElementById('fpmail-brevo-url');
            var regenBtn = document.getElementById('fpmail-brevo-regenerate');
            if (copyBtn && urlInput) {
                copyBtn.addEventListener('click', function() {
                    urlInput.select();
                    document.execCommand('copy');
                    copyBtn.textContent = '<?php echo esc_js(__('Copiato!', 'fp-fpmail')); ?>';
                    setTimeout(function() { copyBtn.textContent = '<?php echo esc_js(__('Copia', 'fp-fpmail')); ?>'; }, 2000);
                });
            }
            if (regenBtn && urlInput) {
                regenBtn.addEventListener('click', function() {
                    regenBtn.disabled = true;
                    var fd = new FormData();
                    fd.append('action', 'fp_fpmail_regenerate_brevo_token');
                    fd.append('nonce', '<?php echo esc_js(wp_create_nonce('fp_fpmail_regenerate_brevo_token')); ?>');
                    fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', { method: 'POST', body: fd, credentials: 'same-origin' })
                    .then(function(r) { return r.json(); })
                    .then(function(d) {
                        if (d.success && d.data && d.data.url) {
                            urlInput.value = d.data.url;
                        }
                    })
                    .finally(function() { regenBtn.disabled = false; });
                });
            }
        })();
        </script>
        <?php
    }
}
