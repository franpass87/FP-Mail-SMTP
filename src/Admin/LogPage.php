<?php
/**
 * Pagina log email.
 *
 * Lista, filtri, paginazione e dettaglio singola email.
 *
 * @package FP\Fpmail\Admin
 */

declare(strict_types=1);

namespace FP\Fpmail\Admin;

/**
 * Pagina admin per il log delle email.
 */
final class LogPage
{
    private const PER_PAGE = 20;

    /**
     * Renderizza la pagina log.
     */
    public function render(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'fp_fpmail_logs';

        // Dettaglio singolo
        $detailId = isset($_GET['detail']) ? absint($_GET['detail']) : 0;
        if ($detailId > 0) {
            $this->renderDetail($detailId, $table);
            return;
        }

        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : '';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
        $offset = ($paged - 1) * self::PER_PAGE;

        $where = ['1=1'];
        $params = [];

        if ($status === 'sent' || $status === 'failed') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        if ($source === 'wp_mail' || $source === 'brevo') {
            $where[] = 'source = %s';
            $params[] = $source;
        }

        if ($search !== '') {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = '(to_addresses LIKE %s OR subject LIKE %s OR from_email LIKE %s OR brevo_message_id LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $whereClause = implode(' AND ', $where);
        $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$whereClause}";
        $total = $params ? (int) $wpdb->get_var($wpdb->prepare($countSql, $params)) : (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        $orderBy = 'created_at DESC';
        $limit = self::PER_PAGE;
        $dataSql = "SELECT * FROM {$table} WHERE {$whereClause} ORDER BY {$orderBy} LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;
        $rows = $params ? $wpdb->get_results($wpdb->prepare($dataSql, $params), ARRAY_A) : [];
        $totalPages = (int) ceil($total / self::PER_PAGE);

        wp_enqueue_style('fp-fpmail-admin', FP_FPMAIL_URL . 'assets/css/admin.css', [], FP_FPMAIL_VERSION);
        ?>
        <div class="wrap fpmail-admin-page">
            <?php /* h1 primo nel .wrap: compat notice JS (.wrap h1).after */ ?>
            <h1 class="screen-reader-text"><?php esc_html_e('Log Email', 'fp-fpmail'); ?></h1>
            <div class="fpmail-page-header">
                <div class="fpmail-page-header-content">
                    <h2 class="fpmail-page-header-title" aria-hidden="true"><span class="dashicons dashicons-list-view"></span> <?php esc_html_e('Log Email', 'fp-fpmail'); ?></h2>
                    <p><?php esc_html_e('Elenco di tutte le email inviate e fallite.', 'fp-fpmail'); ?></p>
                </div>
                <span class="fpmail-page-header-badge">v<?php echo esc_html(FP_FPMAIL_VERSION); ?></span>
            </div>

            <div class="fpmail-card">
                <div class="fpmail-card-header">
                    <div class="fpmail-card-header-left">
                        <span class="dashicons dashicons-filter"></span>
                        <h2><?php esc_html_e('Filtri', 'fp-fpmail'); ?></h2>
                    </div>
                </div>
                <div class="fpmail-card-body">
                    <form method="get" action="" class="fpmail-filters">
                        <input type="hidden" name="page" value="fp-fpmail-logs">
                        <div class="fpmail-filters-row">
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>"
                                   placeholder="<?php esc_attr_e('Cerca destinatario, oggetto...', 'fp-fpmail'); ?>" class="regular-text">
                            <select name="status">
                                <option value=""><?php esc_html_e('Tutti gli stati', 'fp-fpmail'); ?></option>
                                <option value="sent" <?php selected($status, 'sent'); ?>><?php esc_html_e('Inviate', 'fp-fpmail'); ?></option>
                                <option value="failed" <?php selected($status, 'failed'); ?>><?php esc_html_e('Fallite', 'fp-fpmail'); ?></option>
                            </select>
                            <select name="source">
                                <option value=""><?php esc_html_e('Tutte le sorgenti', 'fp-fpmail'); ?></option>
                                <option value="wp_mail" <?php selected($source, 'wp_mail'); ?>><?php esc_html_e('wp_mail', 'fp-fpmail'); ?></option>
                                <option value="brevo" <?php selected($source, 'brevo'); ?>>Brevo</option>
                            </select>
                            <button type="submit" class="fpmail-btn fpmail-btn-secondary"><?php esc_html_e('Filtra', 'fp-fpmail'); ?></button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="fpmail-card">
                <div class="fpmail-card-body fpmail-card-body--no-padding">
                    <table class="fpmail-table wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="fpmail-col-date"><?php esc_html_e('Data', 'fp-fpmail'); ?></th>
                                <th><?php esc_html_e('Destinatari', 'fp-fpmail'); ?></th>
                                <th><?php esc_html_e('Oggetto', 'fp-fpmail'); ?></th>
                                <th class="fpmail-col-source"><?php esc_html_e('Sorgente', 'fp-fpmail'); ?></th>
                                <th class="fpmail-col-status"><?php esc_html_e('Stato', 'fp-fpmail'); ?></th>
                                <th class="fpmail-col-actions"><?php esc_html_e('Azioni', 'fp-fpmail'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($rows)) : ?>
                                <tr>
                                    <td colspan="6"><?php esc_html_e('Nessuna email nel log.', 'fp-fpmail'); ?></td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($rows as $row) :
                                    $rowSource = $row['source'] ?? 'wp_mail';
                                    $detailUrl = admin_url('admin.php?page=fp-fpmail-logs&detail=' . (int) $row['id']);
                                    if ($source !== '') {
                                        $detailUrl = add_query_arg('source', $source, $detailUrl);
                                    }
                                ?>
                                    <tr>
                                        <td><?php echo esc_html(wp_date('d/m/Y H:i', strtotime($row['created_at']))); ?></td>
                                        <td class="fpmail-cell-to"><?php echo esc_html($row['to_addresses']); ?></td>
                                        <td class="fpmail-cell-subject"><?php echo esc_html(mb_substr($row['subject'], 0, 60) . (mb_strlen($row['subject']) > 60 ? '…' : '')); ?></td>
                                        <td>
                                            <span class="fpmail-badge <?php echo $rowSource === 'brevo' ? 'fpmail-badge-info' : 'fpmail-badge-neutral'; ?>">
                                                <?php echo $rowSource === 'brevo' ? 'Brevo' : 'wp_mail'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="fpmail-badge fpmail-badge-<?php echo $row['status'] === 'sent' ? 'success' : 'danger'; ?>">
                                                <?php echo $row['status'] === 'sent' ? esc_html__('Inviata', 'fp-fpmail') : esc_html__('Fallita', 'fp-fpmail'); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo esc_url($detailUrl); ?>"
                                               class="button button-small"><?php esc_html_e('Dettaglio', 'fp-fpmail'); ?></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <?php if ($totalPages > 1) : ?>
                        <div class="fpmail-pagination">
                            <?php
                            $base = add_query_arg(['page' => 'fp-fpmail-logs', 'paged' => '%#%']);
                            if ($status !== '') {
                                $base = add_query_arg('status', $status, $base);
                            }
                            if ($source !== '') {
                                $base = add_query_arg('source', $source, $base);
                            }
                            if ($search !== '') {
                                $base = add_query_arg('s', rawurlencode($search), $base);
                            }
                            echo wp_kses_post(paginate_links([
                                'base' => $base,
                                'format' => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total' => $totalPages,
                                'current' => $paged,
                            ]));
                            ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Renderizza il dettaglio di una singola email.
     *
     * @param int $id ID record.
     * @param string $table Nome tabella.
     */
    private function renderDetail(int $id, string $table): void
    {
        global $wpdb;
        $row = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id),
            ARRAY_A
        );

        if (!$row) {
            wp_die(esc_html__('Record non trovato.', 'fp-fpmail'), '', ['response' => 404]);
        }

        wp_enqueue_style('fp-fpmail-admin', FP_FPMAIL_URL . 'assets/css/admin.css', [], FP_FPMAIL_VERSION);

        $rowSource = $row['source'] ?? 'wp_mail';
        $brevoEvent = $row['brevo_event'] ?? '';
        $brevoMessageId = $row['brevo_message_id'] ?? '';
        $backUrl = admin_url('admin.php?page=fp-fpmail-logs');
        if (isset($_GET['source']) && $_GET['source'] !== '') {
            $backUrl = add_query_arg('source', sanitize_text_field($_GET['source']), $backUrl);
        }
        ?>
        <div class="wrap fpmail-admin-page">
            <?php /* h1 primo nel .wrap: compat notice JS (.wrap h1).after */ ?>
            <h1 class="screen-reader-text"><?php esc_html_e('Dettaglio email', 'fp-fpmail'); ?></h1>
            <div class="fpmail-page-header">
                <div class="fpmail-page-header-content">
                    <h2 class="fpmail-page-header-title" aria-hidden="true"><span class="dashicons dashicons-email"></span> <?php esc_html_e('Dettaglio email', 'fp-fpmail'); ?></h2>
                    <p><?php echo esc_html($row['subject']); ?></p>
                </div>
            </div>

            <p><a href="<?php echo esc_url($backUrl); ?>" class="fpmail-btn fpmail-btn-secondary">&larr; <?php esc_html_e('Torna al log', 'fp-fpmail'); ?></a></p>

            <div class="fpmail-card">
                <div class="fpmail-card-header">
                    <div class="fpmail-card-header-left">
                        <span class="dashicons dashicons-info"></span>
                        <h2><?php esc_html_e('Informazioni', 'fp-fpmail'); ?></h2>
                    </div>
                    <span class="fpmail-badge fpmail-badge-<?php echo $row['status'] === 'sent' ? 'success' : 'danger'; ?>">
                        <?php echo $row['status'] === 'sent' ? esc_html__('Inviata', 'fp-fpmail') : esc_html__('Fallita', 'fp-fpmail'); ?>
                    </span>
                    <span class="fpmail-badge fpmail-badge-neutral fpmail-badge-spacing">
                        <?php echo $rowSource === 'brevo' ? 'Brevo' : 'wp_mail'; ?>
                    </span>
                </div>
                <div class="fpmail-card-body">
                    <dl class="fpmail-detail-dl">
                        <dt><?php esc_html_e('Data', 'fp-fpmail'); ?></dt>
                        <dd><?php echo esc_html(wp_date('d/m/Y H:i:s', strtotime($row['created_at']))); ?></dd>
                        <dt><?php esc_html_e('Destinatari', 'fp-fpmail'); ?></dt>
                        <dd><?php echo esc_html($row['to_addresses']); ?></dd>
                        <dt><?php esc_html_e('Mittente', 'fp-fpmail'); ?></dt>
                        <dd><?php echo esc_html($row['from_email'] ?? ''); ?></dd>
                        <dt><?php esc_html_e('Oggetto', 'fp-fpmail'); ?></dt>
                        <dd><?php echo esc_html($row['subject']); ?></dd>
                        <dt><?php esc_html_e('Allegati', 'fp-fpmail'); ?></dt>
                        <dd><?php echo esc_html((string) ($row['attachments_count'] ?? 0)); ?></dd>
                        <?php if ($rowSource === 'brevo' && $brevoEvent !== '') : ?>
                            <dt><?php esc_html_e('Evento Brevo', 'fp-fpmail'); ?></dt>
                            <dd><code><?php echo esc_html($brevoEvent); ?></code></dd>
                        <?php endif; ?>
                        <?php if ($rowSource === 'brevo' && $brevoMessageId !== '') : ?>
                            <dt><?php esc_html_e('Message-ID Brevo', 'fp-fpmail'); ?></dt>
                            <dd><code class="is-monospace"><?php echo esc_html($brevoMessageId); ?></code></dd>
                        <?php endif; ?>
                        <?php if ($row['status'] === 'failed' && !empty($row['error_message'])) : ?>
                            <dt><?php esc_html_e('Errore', 'fp-fpmail'); ?></dt>
                            <dd class="fpmail-error-msg"><?php echo esc_html($row['error_message']); ?></dd>
                        <?php endif; ?>
                    </dl>
                    <?php
                    $mirrorLink = '';
                    if (!empty($row['headers']) && preg_match('/mirror_link:\s*(\S+)/', $row['headers'], $m)) {
                        $mirrorLink = trim($m[1]);
                    }
                    if ($rowSource === 'brevo' && $mirrorLink !== '' && esc_url_raw($mirrorLink) === $mirrorLink) : ?>
                        <p class="fpmail-mirror-link-row">
                            <a href="<?php echo esc_url($mirrorLink); ?>" target="_blank" rel="noopener" class="fpmail-btn fpmail-btn-secondary">
                                <?php esc_html_e('Apri anteprima Brevo', 'fp-fpmail'); ?> &rarr;
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php
            $previewUrl = add_query_arg(
                [
                    'action' => 'fp_fpmail_log_html',
                    'id' => $id,
                    'nonce' => wp_create_nonce(LogPreviewHandler::nonceAction()),
                ],
                admin_url('admin-ajax.php')
            );
            $hasStoredBody = isset($row['message_body']) && is_string($row['message_body']) && trim($row['message_body']) !== '';
            ?>
            <div class="fpmail-card">
                <div class="fpmail-card-header">
                    <div class="fpmail-card-header-left">
                        <span class="dashicons dashicons-welcome-view-site"></span>
                        <h2><?php esc_html_e('Anteprima messaggio', 'fp-fpmail'); ?></h2>
                    </div>
                </div>
                <div class="fpmail-card-body fpmail-card-body--preview">
                    <p class="description"><?php esc_html_e('Rendering HTML come in un client di posta (script disabilitati per sicurezza).', 'fp-fpmail'); ?></p>
                    <?php if (!$hasStoredBody) : ?>
                        <div class="notice notice-info inline">
                            <p><?php esc_html_e('Per le email inviate prima di questo aggiornamento è disponibile solo il riepilogo testuale sotto.', 'fp-fpmail'); ?></p>
                        </div>
                    <?php endif; ?>
                    <div class="fpmail-log-preview-frame-wrap">
                        <iframe
                            class="fpmail-log-html-preview"
                            title="<?php echo esc_attr__('Anteprima email', 'fp-fpmail'); ?>"
                            sandbox="allow-popups allow-popups-to-escape-sandbox"
                            src="<?php echo esc_url($previewUrl); ?>"
                        ></iframe>
                    </div>
                </div>
            </div>

            <div class="fpmail-card">
                <div class="fpmail-card-header">
                    <div class="fpmail-card-header-left">
                        <span class="dashicons dashicons-editor-alignleft"></span>
                        <h2><?php esc_html_e('Riepilogo testo (senza tag)', 'fp-fpmail'); ?></h2>
                    </div>
                </div>
                <div class="fpmail-card-body">
                    <pre class="fpmail-message-preview"><?php echo esc_html($row['message_preview']); ?></pre>
                </div>
            </div>

            <?php if (!empty($row['headers'])) : ?>
            <div class="fpmail-card">
                <div class="fpmail-card-header">
                    <div class="fpmail-card-header-left">
                        <span class="dashicons dashicons-editor-code"></span>
                        <h2><?php esc_html_e('Headers', 'fp-fpmail'); ?></h2>
                    </div>
                </div>
                <div class="fpmail-card-body">
                    <pre class="fpmail-headers-preview"><?php echo esc_html($row['headers']); ?></pre>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
}
