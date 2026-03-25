<?php
/**
 * Sincronizza eventi transactional Brevo via GET /v3/smtp/statistics/events (API key da FP Tracking).
 *
 * @package FP\Fpmail\Brevo
 */

declare(strict_types=1);

namespace FP\Fpmail\Brevo;

/**
 * Cron: import eventi nel log FP Mail quando ingest method = api.
 */
final class BrevoTransactionalSyncService
{
    private const EVENTS_URL = 'https://api.brevo.com/v3/smtp/statistics/events';

    /**
     * Esegue una sincronizzazione (chiamata da cron).
     */
    public function run(): void
    {
        if (get_option('fp_fpmail_brevo_log_enabled', '0') !== '1') {
            return;
        }

        if (get_option('fp_fpmail_brevo_ingest_method', 'api') !== 'api') {
            return;
        }

        if (!function_exists('fp_tracking_get_brevo_settings') || !function_exists('fp_tracking_brevo_resolve_transactional_site_tag')) {
            return;
        }

        $settings = fp_tracking_get_brevo_settings();
        $apiKey = trim((string) ($settings['api_key'] ?? ''));
        if ($apiKey === '' || empty($settings['enabled'])) {
            return;
        }

        $siteTag = fp_tracking_brevo_resolve_transactional_site_tag();
        $tagFilter = self::build_tag_filter($siteTag);

        [$startDate, $endDate] = self::resolve_date_window();

        $offset = 0;
        $limit = 2500;

        do {
            $args = [
                'startDate' => $startDate,
                'endDate' => $endDate,
                'limit' => $limit,
                'offset' => $offset,
                'sort' => 'desc',
            ];

            if ($siteTag !== '') {
                $args['tags'] = wp_json_encode([$siteTag], JSON_UNESCAPED_SLASHES);
            }

            $url = self::EVENTS_URL . '?' . http_build_query($args, '', '&', PHP_QUERY_RFC3986);

            $response = wp_remote_get(
                $url,
                [
                    'timeout' => 25,
                    'headers' => [
                        'Accept' => 'application/json',
                        'api-key' => $apiKey,
                    ],
                ]
            );

            if (is_wp_error($response)) {
                return;
            }

            $code = (int) wp_remote_retrieve_response_code($response);
            if ($code < 200 || $code >= 300) {
                return;
            }

            $raw = (string) wp_remote_retrieve_body($response);
            $decoded = json_decode($raw, true);
            if (!is_array($decoded) || !isset($decoded['events']) || !is_array($decoded['events'])) {
                return;
            }

            $events = $decoded['events'];
            foreach ($events as $ev) {
                if (!is_array($ev)) {
                    continue;
                }
                BrevoLogIngestor::ingest_api_event_row($ev, $tagFilter);
            }

            $count = count($events);
            $offset += $limit;
        } while ($count >= $limit);

        update_option('fp_fpmail_brevo_sync_last_end_date', $endDate, false);
    }

    /**
     * @return array{0:string,1:string} startDate, endDate (Y-m-d UTC)
     */
    private static function resolve_date_window(): array
    {
        $end = gmdate('Y-m-d');
        $lastEnd = get_option('fp_fpmail_brevo_sync_last_end_date', '');
        if (!is_string($lastEnd) || $lastEnd === '') {
            $start = gmdate('Y-m-d', strtotime('-7 days'));

            return [$start, $end];
        }

        $startTs = strtotime($lastEnd . ' -1 day');
        if ($startTs === false) {
            $start = gmdate('Y-m-d', strtotime('-7 days'));
        } else {
            $start = gmdate('Y-m-d', $startTs);
        }

        $minStart = strtotime('-90 days');
        if ($minStart !== false && strtotime($start) < $minStart) {
            $start = gmdate('Y-m-d', $minStart);
        }

        return [$start, $end];
    }

    /**
     * @return (callable(array): bool)|null
     */
    private static function build_tag_filter(string $siteTag): ?callable
    {
        if ($siteTag === '') {
            return null;
        }

        $needle = strtolower($siteTag);

        return static function (array $row) use ($needle): bool {
            $t = isset($row['tag']) ? strtolower(trim((string) $row['tag'])) : '';
            if ($t === '') {
                return false;
            }
            if ($t === $needle) {
                return true;
            }

            foreach (array_map('trim', explode(',', $t)) as $part) {
                if (strtolower($part) === $needle) {
                    return true;
                }
            }

            return false;
        };
    }

    /**
     * Registra hook cron e intervallo dinamico.
     */
    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'filter_cron_schedules']);
        add_action('fp_fpmail_brevo_sync', [$this, 'run']);
        add_action('init', [$this, 'maybe_schedule'], 30);
    }

    /**
     * @param array<string, array<string, int|string>> $schedules
     *
     * @return array<string, array<string, int|string>>
     */
    public function filter_cron_schedules(array $schedules): array
    {
        $sec = (int) get_option('fp_fpmail_brevo_sync_interval_sec', 900);
        if (!in_array($sec, [300, 900, 1800], true)) {
            $sec = 900;
        }
        $schedules['fp_fpmail_brevo_interval'] = [
            'interval' => $sec,
            'display' => sprintf(
                /* translators: %d: seconds */
                __('FP Mail Brevo sync ogni %d s', 'fp-fpmail'),
                $sec
            ),
        ];

        return $schedules;
    }

    /**
     * Schedula il cron se abilitato (solo se non già presente).
     */
    public function maybe_schedule(): void
    {
        if (get_option('fp_fpmail_brevo_log_enabled', '0') !== '1') {
            return;
        }

        if (get_option('fp_fpmail_brevo_ingest_method', 'api') !== 'api') {
            return;
        }

        if (wp_next_scheduled('fp_fpmail_brevo_sync')) {
            return;
        }

        wp_schedule_event(time() + 60, 'fp_fpmail_brevo_interval', 'fp_fpmail_brevo_sync');
    }

    /**
     * Rimuove tutte le occorrenze del cron Brevo sync.
     */
    public function clear_schedule(): void
    {
        $ts = wp_next_scheduled('fp_fpmail_brevo_sync');
        while ($ts) {
            wp_unschedule_event($ts, 'fp_fpmail_brevo_sync');
            $ts = wp_next_scheduled('fp_fpmail_brevo_sync');
        }
    }

    /**
     * Dopo salvataggio impostazioni: riallinea il cron (intervallo letto da opzione).
     */
    public function reschedule_after_settings_save(): void
    {
        $this->clear_schedule();
        if (get_option('fp_fpmail_brevo_log_enabled', '0') !== '1') {
            return;
        }
        if (get_option('fp_fpmail_brevo_ingest_method', 'api') !== 'api') {
            return;
        }
        wp_schedule_event(time() + 60, 'fp_fpmail_brevo_interval', 'fp_fpmail_brevo_sync');
    }
}
