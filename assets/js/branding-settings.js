/**
 * FP Mail SMTP — branding: Media Library logo + sync colore accent.
 */
(function ($) {
    'use strict';

    function normalizeHex(v) {
        v = (v || '').trim();
        if (v === '') {
            return null;
        }
        if (v.charAt(0) !== '#') {
            v = '#' + v;
        }
        var m3 = /^#([a-f0-9]{3})$/i.exec(v);
        if (m3) {
            var s = m3[1];
            v = '#' + s[0] + s[0] + s[1] + s[1] + s[2] + s[2];
        }
        return /^#[a-f0-9]{6}$/i.test(v) ? v.toLowerCase() : null;
    }

    function initAccentSync() {
        var $hex = $('#fp_fpmail_branding_accent');
        var $color = $('#fp_fpmail_branding_accent_picker');
        if (!$hex.length || !$color.length) {
            return;
        }

        function syncHexToColor() {
            var n = normalizeHex($hex.val());
            if (n) {
                $color.val(n);
            }
        }

        function syncColorToHex() {
            $hex.val($color.val());
        }

        $hex.on('input change', syncHexToColor);
        $color.on('input change', syncColorToHex);
        syncHexToColor();
    }

    function initLogoMedia() {
        var $idInput = $('#fp_fpmail_branding_logo_id');
        var $urlInput = $('#fp_fpmail_branding_logo');
        var $preview = $('#fpmail-branding-logo-preview');
        var frame;

        if (!$idInput.length) {
            return;
        }

        var l10n = window.fpFpmailBranding || {};

        $('#fpmail-branding-select-logo').on('click', function (e) {
            e.preventDefault();
            if (typeof wp === 'undefined' || !wp.media) {
                return;
            }
            if (frame) {
                frame.open();
                return;
            }
            frame = wp.media({
                title: l10n.titleLogo || '',
                library: { type: 'image' },
                multiple: false,
                button: { text: l10n.useImage || l10n.selectLogo || '' }
            });
            frame.on('select', function () {
                var att = frame.state().get('selection').first().toJSON();
                $idInput.val(att.id || 0);
                var thumbUrl = '';
                if (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) {
                    thumbUrl = att.sizes.thumbnail.url;
                } else if (att.url) {
                    thumbUrl = att.url;
                }
                if (thumbUrl) {
                    $preview.html('<img src="' + thumbUrl + '" alt="" width="150" height="150" loading="lazy" />').show();
                }
                if (att.url) {
                    $urlInput.val(att.url);
                }
                $(document).trigger('fpmailBrandingFormChanged');
            });
            frame.open();
        });

        $('#fpmail-branding-remove-logo').on('click', function (e) {
            e.preventDefault();
            $idInput.val('0');
            $preview.empty().hide();
            $urlInput.val('');
            $(document).trigger('fpmailBrandingFormChanged');
        });
    }

    /**
     * Anteprima email: stessi valori del form, via AJAX (BrandingService + branding_override).
     */
    function initLiveBrandingPreview() {
        var l10n = window.fpFpmailBranding || {};
        var $light = $('#fpmail-email-preview-light');
        var $dark = $('#fpmail-email-preview-dark');
        if (!$light.length || !$dark.length || !l10n.previewNonce || !l10n.previewAction || !l10n.ajaxUrl) {
            return;
        }

        var timer = null;
        var previewReqId = 0;

        function collectFormData() {
            var fd = new FormData();
            fd.append('action', l10n.previewAction);
            fd.append('nonce', l10n.previewNonce);
            fd.append('fp_fpmail_email_branding[logo_attachment_id]', $('#fp_fpmail_branding_logo_id').val() || '0');
            fd.append('fp_fpmail_email_branding[logo]', $('#fp_fpmail_branding_logo').val() || '');
            fd.append('fp_fpmail_email_branding[logo_width]', $('#fp_fpmail_branding_logo_w').val() || '0');
            fd.append('fp_fpmail_email_branding[logo_height]', $('#fp_fpmail_branding_logo_h').val() || '0');
            fd.append('fp_fpmail_email_branding[accent_color]', $('#fp_fpmail_branding_accent').val() || '');
            fd.append('fp_fpmail_email_branding[header_text]', $('#fp_fpmail_branding_header').val() || '');
            fd.append('fp_fpmail_email_branding[footer_text]', $('#fp_fpmail_branding_footer').val() || '');
            return fd;
        }

        function run() {
            var myId = ++previewReqId;
            $light.addClass('fpmail-email-preview-shell--loading');
            $dark.addClass('fpmail-email-preview-shell--loading');

            fetch(l10n.ajaxUrl, {
                method: 'POST',
                body: collectFormData(),
                credentials: 'same-origin'
            })
                .then(function (r) {
                    return r.json();
                })
                .then(function (data) {
                    if (myId !== previewReqId) {
                        return;
                    }
                    if (data && data.success && data.data && data.data.light && data.data.dark) {
                        $light.html(data.data.light);
                        $dark.html(data.data.dark);
                    } else if (l10n.previewError) {
                        window.console.warn(l10n.previewError);
                    }
                })
                .catch(function () {
                    if (myId !== previewReqId) {
                        return;
                    }
                    if (l10n.previewError) {
                        window.console.warn(l10n.previewError);
                    }
                })
                .finally(function () {
                    if (myId === previewReqId) {
                        $light.removeClass('fpmail-email-preview-shell--loading');
                        $dark.removeClass('fpmail-email-preview-shell--loading');
                    }
                });
        }

        function schedule() {
            clearTimeout(timer);
            timer = setTimeout(run, 350);
        }

        var selectors = [
            '#fp_fpmail_branding_logo',
            '#fp_fpmail_branding_logo_w',
            '#fp_fpmail_branding_logo_h',
            '#fp_fpmail_branding_accent',
            '#fp_fpmail_branding_accent_picker',
            '#fp_fpmail_branding_header',
            '#fp_fpmail_branding_footer'
        ].join(', ');

        $(document).on('input change', selectors, schedule);
        $(document).on('fpmailBrandingFormChanged', schedule);
    }

    $(function () {
        initAccentSync();
        initLogoMedia();
        initLiveBrandingPreview();
    });
}(jQuery));
