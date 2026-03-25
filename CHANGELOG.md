# Changelog

## [1.3.0] - 2026-03-24

### Added

- Log eventi Brevo transactional: **sincronizzazione via API** (`GET /v3/smtp/statistics/events`) con chiave da **FP Marketing Tracking Layer**; cron personalizzabile (5 / 15 / 30 minuti); filtro per tag sito (`transactional_site_tag` nel layer) e dedup su message id + evento.
- Opzioni `fp_fpmail_brevo_ingest_method` (predefinito **API**) e `fp_fpmail_brevo_sync_interval_sec`; cursore `fp_fpmail_brevo_sync_last_end_date` per finestra temporale con overlap.
- Servizi `BrevoTransactionalSyncService` e `BrevoLogIngestor` (mapping eventi API ↔ stati log condivisi col webhook).

### Changed

- Webhook Brevo: accettato solo se modalità **webhook** esplicita; in modalità API il token non è valido per l’ingest.

## [1.2.3] - 2026-03-24

### Added

- Anteprima branding: **due colonne** (tema chiaro sempre forzato + simulazione tema scuro); opzioni `wrap()` `preview_mode` e `include_branding_styles`.

### Fixed

- Anteprima admin: con sistema in dark mode non forza più solo il tema scuro — la colonna «Tema chiaro» usa `fp-fpmail-email--preview-light` esclusa dalla media query `prefers-color-scheme: dark`.

### Changed

- Email in produzione: contenuto avvolto in `div.fp-fpmail-email-root` così i selettori dark mode restano corretti; regole `.fp-fpmail-email--preview-dark` solo per anteprima.

## [1.2.2] - 2026-03-24

### Added

- Branding email: stili **dark mode** con `@media (prefers-color-scheme: dark)` e regole `[data-ogsc]` (Outlook.com tema scuro): sfondi, testi, link e gradient header attenuato dall’accent configurato; classi `fp-fpmail-email-*` sul layout; `color-scheme: light dark` sul wrapper esterno.

## [1.2.1] - 2026-03-24

### Added

- Branding: logo da Media Library (`logo_attachment_id`) con anteprima; priorità sull’URL manuale in `wrap()`.
- Impostazioni: selettore colore accent affiancato all’hex; script `branding-settings.js` (sync hex ↔ color, frame media).

### Changed

- Header email: con logo, titolo vuoto non usa più il fallback al nome sito nel banner; senza logo resta il fallback. Preheader: titolo se impostato, altrimenti nome sito.
- Footer branding: HTML consentito e sanificato con `wp_kses_post` (stesso modello dei post); textarea e hint aggiornati.

## [1.2.0] - 2026-03-24

### Added

- Branding email unificato (layout allineato a FP Experiences): opzioni logo, dimensioni, colore accent, header, footer; anteprima in Impostazioni.
- Filtro `fp_fpmail_brand_html` e funzione `fp_fpmail_brand_html()` per uso da altri plugin FP (corpo messaggio invariato, solo wrapper grafico).
- Opzioni `fp_fpmail_email_branding`, `fp_fpmail_branding_enabled`; toggle per disattivare il wrapper senza disinstallare il plugin.

## [1.1.4] - 2026-03-23

### Changed

- Menu position 56.5 per ordine alfabetico FP.

## [1.1.3] - 2025-03-19

### Fixed

- Admin: `h1.screen-reader-text` primo nel `.wrap` + titolo banner in `h2` (Impostazioni, Log, Dettaglio); compat notice JS `.wrap h1`; CSS aggiornato per titolo e `.notice` sul `.wrap`.

## [1.1.2] - 2025-03-19

### Fixed

- Admin CSS: rimosso flex/`order` su `#wpbody-content` che spostava le notice WordPress sotto il contenuto; allineato a `fp-admin-ui-design-system.mdc` (`margin-top` sul `.wrap`).

## [1.1.1] - 2025-03-19

### Fixed

- Header injection: sanitizzazione `\r` e `\n` nel subject (MailLogger, BrevoWebhookController, email di test)
- `wp_die`: risposta HTTP 403/404 corretta al posto di 200

### Security

- Rate limiting sul webhook Brevo (60 req/min per IP) per prevenire abusi
- Header injection prevenuto su tutti i subject email

### Changed

- Costante `FP_FPMAIL_BASENAME` aggiunta (architettura FP)
- `declare(strict_types=1)` nel main file
- Stili inline spostati in CSS (design system: `.fpmail-card-body--no-padding`, `.fpmail-field-spacing`, ecc.)

## [1.1.0] - 2025-03-19

### Added

- Integrazione Brevo: webhook per eventi transactional (sent, delivered, bounce, ecc.)
- Log unificato wp_mail + Brevo in un'unica vista
- Filtro per sorgente (wp_mail / Brevo) nella pagina Log
- Colonna "Sorgente" e dettaglio eventi Brevo (brevo_event, message-id, link mirror)
- Impostazioni Brevo: toggle abilitazione, token webhook, URL da configurare in Brevo

### Changed

- Tabella log: colonne source, brevo_event, brevo_message_id (migrazione 1.1)
- Ricerca log: include brevo_message_id
- Webhook Brevo: fallback su body raw se get_json_params vuoto

## [1.0.0] - 2025-03-19

### Added

- Configurazione SMTP (host, porta, crittografia, auth)
- Override mittente (From email, From name)
- Log completo email in uscita (successo/fallimento)
- Pagina admin Log con filtri, ricerca, paginazione
- Dettaglio singola email con corpo e headers
- Retention configurabile (1–365 giorni) con cron giornaliero
- Email di test
- Design system FP unificato
