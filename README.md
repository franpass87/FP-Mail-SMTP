# FP Mail SMTP

[![version](https://img.shields.io/badge/version-1.4.0-blue.svg)](https://github.com/franpass87/FP-Mail-SMTP)

Plugin WordPress per la configurazione SMTP e il log di tutte le email in uscita. Compatibile con tutti i plugin FP, WooCommerce e qualsiasi plugin che usa `wp_mail()`.

## Funzionalità

- **Configurazione SMTP**: host, porta, crittografia (SSL/TLS), autenticazione
- **Mittente predefinito**: override di From email e From name
- **Log completo**: registrazione di tutte le email inviate e fallite (wp_mail)
- **Integrazione Brevo**: log eventi transactional da **API** (predefinito, chiave da FP Marketing Tracking Layer) oppure webhook; tag sito dal layer per filtrare gli eventi in multi-installazione
- **Pagina Log**: filtri per stato e sorgente, ricerca, paginazione, dettaglio con **anteprima HTML** (iframe) e riepilogo testuale
- **Retention configurabile**: pulizia automatica dei log (1–365 giorni)
- **Email di test**: verifica la configurazione con un click
- **Branding email FP**: wrapper HTML unificato (stile FP Experiences) in Impostazioni — logo da URL o da Media Library, colore accent (hex + picker), header/footer (footer con HTML limitato tipo post); stili opzionali per **dark mode** del client (`prefers-color-scheme: dark`, Outlook.com `[data-ogsc]`); altri plugin possono usare `fp_fpmail_brand_html()` o il filtro `fp_fpmail_brand_html` sul corpo HTML senza cambiare i testi

## Requisiti

- WordPress 6.0+
- PHP 8.0+

## Installazione

1. Carica la cartella del plugin in `wp-content/plugins/`
2. Attiva il plugin dalla schermata Plugin
3. Vai in **FP Mail SMTP > Impostazioni** e configura SMTP
4. Controlla il log in **FP Mail SMTP > Log Email**

## Configurazione

### SMTP

Inserisci i dati del tuo provider SMTP (Gmail, SendGrid, Mailgun, server aziendale, ecc.):

- **Host**: es. `smtp.gmail.com`
- **Porta**: 25, 465 (SSL), 587 (TLS)
- **Crittografia**: nessuna, SSL o TLS
- **Username/Password**: credenziali SMTP

Se lasci l'host vuoto, WordPress userà la funzione `mail()` PHP.

### Log

- **Abilita log**: attiva/disattiva la registrazione
- **Retention**: giorni di conservazione (default 30). I log più vecchi vengono eliminati automaticamente.

## Compatibilità

Il plugin è compatibile con:

- Tutti i plugin FP (FP-Experiences, FP-Restaurant-Reservations, FP-Forms, FP-Digital-Marketing-Suite, ecc.)
- WooCommerce
- Qualsiasi plugin che invia email tramite `wp_mail()`

Non modifica il flusso di invio: si aggancia a `phpmailer_init` per SMTP e a `wp_mail_succeeded`/`wp_mail_failed` per il log.

## Struttura tecnica

| Elemento | Valore |
|----------|--------|
| Tabella DB | `wp_fp_fpmail_logs` |
| Opzioni | `fp_fpmail_smtp_*`, `fp_fpmail_from_*`, `fp_fpmail_log_*`, `fp_fpmail_brevo_*`, `fp_fpmail_email_branding`, `fp_fpmail_branding_enabled` |
| Hook | `phpmailer_init`, `wp_mail_succeeded`, `wp_mail_failed`, `wp_ajax_fp_fpmail_log_html`, filtro `fp_fpmail_brand_html` |
| API | Funzione `fp_fpmail_brand_html( string $html, array $args = [] ): string`; REST webhook Brevo (solo se modalità webhook) |
| Cron | `fp_fpmail_cleanup_logs` (giornaliero); `fp_fpmail_brevo_sync` (intervallo 5/15/30 min in modalità API) |

## Autore

**Francesco Passeri**

- Sito: [francescopasseri.com](https://francescopasseri.com)
- Email: [info@francescopasseri.com](mailto:info@francescopasseri.com)
- GitHub: [github.com/franpass87](https://github.com/franpass87)
