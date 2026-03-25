=== FP Mail SMTP ===

Contributors: franpass87
Tags: smtp, email, mail, log
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.2.3
Requires PHP: 8.0
License: Proprietary
License URI: https://francescopasseri.com

Configurazione SMTP e log completo di tutte le email in uscita. Compatibile con tutti i plugin FP.

== Description ==

Plugin WordPress per la configurazione SMTP e il log di tutte le email in uscita. Compatibile con tutti i plugin FP, WooCommerce e qualsiasi plugin che usa wp_mail().

= Funzionalità =

* Configurazione SMTP: host, porta, crittografia (SSL/TLS), autenticazione
* Mittente predefinito: override di From email e From name
* Log completo: registrazione di tutte le email inviate e fallite
* Pagina Log: filtri per stato, ricerca, paginazione, dettaglio singola email
* Retention configurabile: pulizia automatica dei log (1–365 giorni)
* Email di test
* Branding email unificato per plugin FP (filtro fp_fpmail_brand_html, anteprima in Impostazioni)

== Changelog ==

= 1.2.3 = (2026-03-24)
* Anteprima branding: tema chiaro (sempre) + tema scuro (simulazione); wrap() con preview_mode.

= 1.2.2 = (2026-03-24)
* Branding: stili dark mode (prefers-color-scheme, Outlook data-ogsc), classi fp-fpmail-email-*.

= 1.2.1 = (2026-03-24)
* Branding: logo da libreria media, color picker accent, regole titolo con/senza logo, footer HTML (wp_kses_post).

= 1.2.0 = (2026-03-24)
* Branding email unificato (layout FP Experiences): logo, colori, header, footer, anteprima.
* Filtro `fp_fpmail_brand_html` e funzione `fp_fpmail_brand_html()` per gli altri plugin FP.

= 1.1.4 = (2026-03-23)
* Menu position 56.5 per ordine alfabetico FP.

= 1.1.3 = (2025-03-19)
* Fix: notice terze parti — h1 screen-reader + titolo h2 nel banner (compat .wrap h1 JS).

= 1.1.2 = (2025-03-19)
* Fix: layout admin — notice WordPress in ordine corretto (niente flex/order su #wpbody-content).

= 1.1.1 = (2025-03-19)
* Fix: header injection nel subject, wp_die 403/404
* Security: rate limiting webhook Brevo
* Stili inline spostati in CSS

= 1.1.0 = (2025-03-19)
* Integrazione Brevo: webhook per eventi transactional
* Log unificato wp_mail + Brevo, filtro sorgente

= 1.0.0 = (2025-03-19)
* Rilascio iniziale
