# Changelog

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
