# Changelog

## [1.1.0] - 2025-03-19

### Added

- Integrazione Brevo: webhook per eventi transactional (sent, delivered, bounce, ecc.)
- Log unificato wp_mail + Brevo in un'unica vista
- Filtro per sorgente (wp_mail / Brevo) nella pagina Log
- Colonna "Sorgente" e dettaglio eventi Brevo (brevo_event, message-id, link mirror)
- Impostazioni Brevo: toggle abilitazione, token webhook, URL da configurare in Brevo

### Changed

- Tabella log: colonne source, brevo_event, brevo_message_id (migrazione 1.1)

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
