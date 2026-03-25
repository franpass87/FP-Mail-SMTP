<?php
/**
 * Wrapper HTML email allineato a FP Experiences (layout tabellare, gradient, footer).
 *
 * @package FP\Fpmail\Branding
 */

declare(strict_types=1);

namespace FP\Fpmail\Branding;

/**
 * Registra il filtro `fp_fpmail_brand_html` e costruisce il markup di branding.
 */
final class BrandingService
{
    public const OPTION_BRANDING = 'fp_fpmail_email_branding';

    public const OPTION_ENABLED = 'fp_fpmail_branding_enabled';

    private const DEFAULT_ACCENT = '#0b7285';

    public function register(): void
    {
        add_filter('fp_fpmail_brand_html', [$this, 'filterBrandHtml'], 10, 2);
    }

    /**
     * Filtro WordPress: avvolge l’HTML interno con il layout FP unificato.
     *
     * @param array<string, mixed> $args Chiavi: `skip_branding` (bool) per saltare il wrapper.
     */
    public function filterBrandHtml(string $html, array $args = []): string
    {
        if (! empty($args['skip_branding'])) {
            return $html;
        }

        if (get_option(self::OPTION_ENABLED, '1') !== '1') {
            return $html;
        }

        return $this->wrap($html);
    }

    /**
     * Applica il wrapper usando le opzioni salvate in `fp_fpmail_email_branding`.
     *
     * Include stili per dark mode: `@media (prefers-color-scheme: dark)` (escluso dentro
     * `.fp-fpmail-email--preview-light` per l’anteprima admin) e `[data-ogsc]` (Outlook.com).
     * Per simulare il tema scuro in anteprima usare `preview_mode` => `'dark'`.
     *
     * @param array{
     *     include_branding_styles?: bool,
     *     preview_mode?: 'light'|'dark'|null,
     * } $options `include_branding_styles` false evita di ripetere il blocco style (seconda anteprima).
     */
    public function wrap(string $message, array $options = []): string
    {
        if ('' === trim($message)) {
            return '';
        }

        $include_branding_styles = $options['include_branding_styles'] ?? true;
        $preview_mode = $options['preview_mode'] ?? null;
        if ($preview_mode !== null && $preview_mode !== 'light' && $preview_mode !== 'dark') {
            $preview_mode = null;
        }

        $branding = get_option(self::OPTION_BRANDING, []);
        $branding = is_array($branding) ? $branding : [];

        $logo_url = self::resolveLogoUrl($branding);
        $has_logo = $logo_url !== '';

        $logo_width = isset($branding['logo_width']) ? (int) $branding['logo_width'] : 0;
        $logo_height = isset($branding['logo_height']) ? (int) $branding['logo_height'] : 0;

        $accent_raw = isset($branding['accent_color']) ? trim((string) $branding['accent_color']) : '';
        $accent_color = self::sanitizeHex($accent_raw) ?? self::DEFAULT_ACCENT;

        $header_input = isset($branding['header_text']) ? trim((string) $branding['header_text']) : '';
        $footer_text = isset($branding['footer_text']) ? trim((string) $branding['footer_text']) : '';

        $site_name = (string) get_bloginfo('name');

        // Preheader: titolo impostato o nome sito.
        $preheader_text = $header_input !== '' ? $header_input : $site_name;

        // Titolo visibile nel banner: con logo il titolo è opzionale (nessun fallback a site_name); senza logo si usa il nome sito se vuoto.
        if ($has_logo) {
            $title_visible = $header_input;
        } else {
            $title_visible = $header_input !== '' ? $header_input : $site_name;
        }

        $img_alt = $title_visible !== '' ? $title_visible : $site_name;

        $logo_style = 'margin:0 auto 12px;display:block;';
        $logo_style .= $logo_width > 0 ? 'max-width:' . $logo_width . 'px;' : 'max-width:180px;';
        $logo_style .= $logo_height > 0 ? 'max-height:' . $logo_height . 'px;width:auto;height:auto;' : 'height:auto;';

        $accent_dark = self::darkenHex($accent_color, 30);

        $root_classes = ['fp-fpmail-email-root'];
        if ($preview_mode === 'light') {
            $root_classes[] = 'fp-fpmail-email--preview-light';
        } elseif ($preview_mode === 'dark') {
            $root_classes[] = 'fp-fpmail-email--preview-dark';
        }
        $root_class_attr = implode(' ', $root_classes);

        $color_scheme_inline = 'light dark';
        if ($preview_mode === 'light') {
            $color_scheme_inline = 'light';
        }

        ob_start();
        if ($include_branding_styles) {
            echo self::darkModeEmailStyleBlock($accent_color);
        }
        ?>
        <div class="<?php echo esc_attr($root_class_attr); ?>">
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
            <?php echo esc_html($preheader_text); ?>
        </div>
        <table role="presentation" class="fp-fpmail-email-outer" cellpadding="0" cellspacing="0" width="100%" style="margin:0;padding:0;background-color:#eef2f7;color-scheme:<?php echo esc_attr($color_scheme_inline); ?>;">
            <tr>
                <td align="center" class="fp-fpmail-email-shell" style="padding:28px 12px;">
                    <table role="presentation" class="fp-fpmail-email-card" cellpadding="0" cellspacing="0" width="640" style="width:640px;max-width:640px;background-color:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dbe3ef;">
                        <tr>
                            <td class="fp-fpmail-email-header" style="background:linear-gradient(135deg,<?php echo esc_attr($accent_color); ?> 0%,<?php echo esc_attr($accent_dark); ?> 100%);padding:24px 28px;text-align:center;color:#ffffff;font-family:'Helvetica Neue',Arial,sans-serif;">
                                <?php if ($has_logo) : ?>
                                    <img class="fp-fpmail-email-logo" src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($img_alt); ?>" style="<?php echo esc_attr($logo_style); ?>" />
                                <?php endif; ?>
                                <?php if ($title_visible !== '') : ?>
                                    <p class="fp-fpmail-email-header-title" style="margin:0;font-size:19px;font-weight:700;letter-spacing:0.25px;"><?php echo esc_html($title_visible); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fp-fpmail-email-body" style="padding:30px 30px 22px;color:#0f172a;font-family:'Helvetica Neue',Arial,sans-serif;line-height:1.75;font-size:15px;background:#ffffff;">
                                <?php echo wp_kses_post($message); ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="fp-fpmail-email-footer" style="padding:18px 30px;background-color:#f8fafc;color:#475569;font-size:13px;text-align:center;font-family:'Helvetica Neue',Arial,sans-serif;border-top:1px solid #e2e8f0;">
                                <?php if ($footer_text !== '') : ?>
                                    <div class="fp-fpmail-footer-html fp-fpmail-email-footer-html" style="margin:0;"><?php echo wp_kses_post($footer_text); ?></div>
                                <?php else : ?>
                                    <p class="fp-fpmail-email-footer-text" style="margin:0;"><?php echo esc_html($this->defaultFooterText()); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        </div>
        <?php

        return trim((string) ob_get_clean());
    }

    /**
     * URL logo: allegato Media Library se valido, altrimenti campo URL manuale.
     *
     * @param array<string, mixed> $branding
     */
    public static function resolveLogoUrl(array $branding): string
    {
        $aid = isset($branding['logo_attachment_id']) ? absint($branding['logo_attachment_id']) : 0;
        if ($aid > 0 && wp_attachment_is_image($aid)) {
            $url = wp_get_attachment_image_url($aid, 'full');
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        $logo = isset($branding['logo']) ? (string) $branding['logo'] : '';
        $logo = esc_url_raw($logo);

        return $logo !== '' ? $logo : '';
    }

    /**
     * Sanifica i dati inviati dal form impostazioni.
     *
     * @param array<string, mixed> $input
     *
     * @return array{logo:string,logo_attachment_id:int,logo_width:int,logo_height:int,accent_color:string,header_text:string,footer_text:string}
     */
    public static function sanitizeBrandingInput(array $input): array
    {
        $logo = isset($input['logo']) ? esc_url_raw((string) $input['logo']) : '';
        $logo_attachment_id = self::sanitizeLogoAttachmentId($input['logo_attachment_id'] ?? 0);

        $footer_raw = isset($input['footer_text']) ? (string) $input['footer_text'] : '';

        return [
            'logo' => $logo,
            'logo_attachment_id' => $logo_attachment_id,
            'logo_width' => isset($input['logo_width']) ? max(0, min(600, absint($input['logo_width']))) : 0,
            'logo_height' => isset($input['logo_height']) ? max(0, min(400, absint($input['logo_height']))) : 0,
            'accent_color' => (string) (self::sanitizeHex((string) ($input['accent_color'] ?? '')) ?? ''),
            'header_text' => isset($input['header_text']) ? sanitize_text_field((string) $input['header_text']) : '',
            'footer_text' => wp_kses_post($footer_raw),
        ];
    }

    /**
     * Hex a 6 cifre per input type="color" e coerenza form.
     */
    public static function normalizedAccentColor(string $stored): string
    {
        $san = self::sanitizeHex(trim($stored)) ?? self::DEFAULT_ACCENT;
        $h = ltrim($san, '#');
        if (strlen($h) === 3 && ctype_xdigit($h)) {
            return '#' . strtolower($h[0] . $h[0] . $h[1] . $h[1] . $h[2] . $h[2]);
        }

        return strlen($h) === 6 && ctype_xdigit($h) ? '#' . strtolower($h) : self::DEFAULT_ACCENT;
    }

    /**
     * Valida ID allegato immagine e permessi utente corrente.
     *
     * @param mixed $raw ID allegato dal form.
     */
    public static function sanitizeLogoAttachmentId(mixed $raw): int
    {
        $id = absint($raw);
        if ($id <= 0) {
            return 0;
        }

        if (get_post_type($id) !== 'attachment') {
            return 0;
        }

        $mime = (string) get_post_mime_type($id);
        if ($mime === '' || ! str_starts_with($mime, 'image/')) {
            return 0;
        }

        if (! current_user_can('edit_post', $id)) {
            return 0;
        }

        return $id;
    }

    private function defaultFooterText(): string
    {
        return (string) __(
            'Messaggio generato automaticamente. Per assistenza puoi rispondere a questa email.',
            'fp-fpmail'
        );
    }

    /**
     * CSS dark: media query (non dentro anteprima light), Outlook [data-ogsc], anteprima admin forzata (.fp-fpmail-email--preview-dark).
     *
     * @param string $h0 Primo stop gradient header (esc_attred).
     * @param string $h1 Secondo stop gradient header (esc_attred).
     */
    private static function darkEmailCssRules(string $selector_prefix, string $h0, string $h1): string
    {
        return $selector_prefix . '.fp-fpmail-email-outer{background-color:#12141a!important;}'
            . $selector_prefix . '.fp-fpmail-email-shell{background-color:transparent!important;}'
            . $selector_prefix . '.fp-fpmail-email-card{background-color:#1c1f26!important;border-color:#363d4d!important;}'
            . $selector_prefix . '.fp-fpmail-email-header{background:linear-gradient(135deg,' . $h0 . ' 0%,' . $h1 . ' 100%)!important;color:#f8fafc!important;}'
            . $selector_prefix . '.fp-fpmail-email-header .fp-fpmail-email-header-title{color:#f8fafc!important;}'
            . $selector_prefix . '.fp-fpmail-email-body{background-color:#1c1f26!important;color:#e2e8f0!important;}'
            . $selector_prefix . '.fp-fpmail-email-body a{color:#38bdf8!important;}'
            . $selector_prefix . '.fp-fpmail-email-footer{background-color:#151922!important;color:#94a3b8!important;border-top-color:#363d4d!important;}'
            . $selector_prefix . '.fp-fpmail-email-footer .fp-fpmail-email-footer-text{color:#94a3b8!important;}'
            . $selector_prefix . '.fp-fpmail-email-footer a,' . $selector_prefix . '.fp-fpmail-email-footer-html a{color:#7dd3fc!important;}';
    }

    /**
     * CSS per dark mode (Apple Mail, alcuni client WebKit, Outlook.com con [data-ogsc]).
     * I colori accent nel gradient header sono derivati dall’accent utente (già sanificato).
     */
    private static function darkModeEmailStyleBlock(string $accent_color): string
    {
        $h0 = esc_attr(self::darkenHex($accent_color, 15));
        $h1 = esc_attr(self::darkenHex($accent_color, 50));

        $media_inner = self::darkEmailCssRules('.fp-fpmail-email-root:not(.fp-fpmail-email--preview-light) ', $h0, $h1);
        $ogsc_rules = self::darkEmailCssRules('[data-ogsc] ', $h0, $h1);
        $preview_dark_rules = self::darkEmailCssRules('.fp-fpmail-email--preview-dark ', $h0, $h1);

        return '<style type="text/css">'
            . '@media (prefers-color-scheme: dark){' . $media_inner . '}'
            . $ogsc_rules
            . $preview_dark_rules
            . '</style>';
    }

    private static function sanitizeHex(string $hex): ?string
    {
        $hex = trim($hex);
        if ($hex === '') {
            return null;
        }

        $san = sanitize_hex_color($hex);

        return is_string($san) && $san !== '' ? $san : null;
    }

    private static function darkenHex(string $hex, int $percent): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            $hex = ltrim(self::DEFAULT_ACCENT, '#');
        }
        $r = max(0, (int) round(hexdec(substr($hex, 0, 2)) * (1 - $percent / 100)));
        $g = max(0, (int) round(hexdec(substr($hex, 2, 2)) * (1 - $percent / 100)));
        $b = max(0, (int) round(hexdec(substr($hex, 4, 2)) * (1 - $percent / 100)));

        return sprintf('#%02x%02x%02x', $r, $g, $b);
    }
}
