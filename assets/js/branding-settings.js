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
            });
            frame.open();
        });

        $('#fpmail-branding-remove-logo').on('click', function (e) {
            e.preventDefault();
            $idInput.val('0');
            $preview.empty().hide();
            $urlInput.val('');
        });
    }

    $(function () {
        initAccentSync();
        initLogoMedia();
    });
}(jQuery));
