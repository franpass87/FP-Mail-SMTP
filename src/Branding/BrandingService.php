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
     */
    public function wrap(string $message): string
    {
        if ('' === trim($message)) {
            return '';
        }

        $branding = get_option(self::OPTION_BRANDING, []);
        $branding = is_array($branding) ? $branding : [];

        $logo = isset($branding['logo']) ? esc_url((string) $branding['logo']) : '';
        $logo_width = isset($branding['logo_width']) ? (int) $branding['logo_width'] : 0;
        $logo_height = isset($branding['logo_height']) ? (int) $branding['logo_height'] : 0;

        $accent_raw = isset($branding['accent_color']) ? trim((string) $branding['accent_color']) : '';
        $accent_color = self::sanitizeHex($accent_raw) ?? self::DEFAULT_ACCENT;

        $header_text = isset($branding['header_text']) ? trim((string) $branding['header_text']) : '';
        $footer_text = isset($branding['footer_text']) ? trim((string) $branding['footer_text']) : '';

        $site_name = (string) get_bloginfo('name');

        if ('' === $header_text) {
            $header_text = $site_name;
        }

        $logo_style = 'margin:0 auto 12px;display:block;';
        $logo_style .= $logo_width > 0 ? 'max-width:' . $logo_width . 'px;' : 'max-width:180px;';
        $logo_style .= $logo_height > 0 ? 'max-height:' . $logo_height . 'px;width:auto;height:auto;' : 'height:auto;';

        $accent_dark = self::darkenHex($accent_color, 30);

        ob_start();
        ?>
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;">
            <?php echo esc_html($header_text); ?>
        </div>
        <table role="presentation" cellpadding="0" cellspacing="0" width="100%" style="margin:0;padding:0;background-color:#eef2f7;">
            <tr>
                <td align="center" style="padding:28px 12px;">
                    <table role="presentation" cellpadding="0" cellspacing="0" width="640" style="width:640px;max-width:640px;background-color:#ffffff;border-radius:18px;overflow:hidden;border:1px solid #dbe3ef;">
                        <tr>
                            <td style="background:linear-gradient(135deg,<?php echo esc_attr($accent_color); ?> 0%,<?php echo esc_attr($accent_dark); ?> 100%);padding:24px 28px;text-align:center;color:#ffffff;font-family:'Helvetica Neue',Arial,sans-serif;">
                                <?php if ($logo !== '') : ?>
                                    <img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($header_text); ?>" style="<?php echo esc_attr($logo_style); ?>" />
                                <?php endif; ?>
                                <?php if ($header_text !== '') : ?>
                                    <p style="margin:0;font-size:19px;font-weight:700;letter-spacing:0.25px;"><?php echo esc_html($header_text); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:30px 30px 22px;color:#0f172a;font-family:'Helvetica Neue',Arial,sans-serif;line-height:1.75;font-size:15px;background:#ffffff;">
                                <?php echo wp_kses_post($message); ?>
                            </td>
                        </tr>
                        <tr>
                            <td style="padding:18px 30px;background-color:#f8fafc;color:#475569;font-size:13px;text-align:center;font-family:'Helvetica Neue',Arial,sans-serif;border-top:1px solid #e2e8f0;">
                                <?php if ($footer_text !== '') : ?>
                                    <p style="margin:0;"><?php echo nl2br(esc_html($footer_text)); ?></p>
                                <?php else : ?>
                                    <p style="margin:0;"><?php echo esc_html($this->defaultFooterText()); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        <?php

        return trim((string) ob_get_clean());
    }

    /**
     * Sanifica i dati inviati dal form impostazioni.
     *
     * @param array<string, mixed> $input
     *
     * @return array{logo:string,logo_width:int,logo_height:int,accent_color:string,header_text:string,footer_text:string}
     */
    public static function sanitizeBrandingInput(array $input): array
    {
        $logo = isset($input['logo']) ? esc_url_raw((string) $input['logo']) : '';

        return [
            'logo' => $logo,
            'logo_width' => isset($input['logo_width']) ? max(0, min(600, absint($input['logo_width']))) : 0,
            'logo_height' => isset($input['logo_height']) ? max(0, min(400, absint($input['logo_height']))) : 0,
            'accent_color' => (string) (self::sanitizeHex((string) ($input['accent_color'] ?? '')) ?? ''),
            'header_text' => isset($input['header_text']) ? sanitize_text_field((string) $input['header_text']) : '',
            'footer_text' => isset($input['footer_text']) ? sanitize_textarea_field((string) $input['footer_text']) : '',
        ];
    }

    private function defaultFooterText(): string
    {
        return (string) __(
            'Messaggio generato automaticamente. Per assistenza puoi rispondere a questa email.',
            'fp-fpmail'
        );
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
