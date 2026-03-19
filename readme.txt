=== FP Mail SMTP ===

Contributors: franpass87
Tags: smtp, email, mail, log
Requires at least: 6.0
Tested up to: 6.7
Stable tag: 1.1.0
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

== Changelog ==

= 1.1.0 = (2025-03-19)
* Integrazione Brevo: webhook per eventi transactional
* Log unificato wp_mail + Brevo, filtro sorgente

= 1.0.0 = (2025-03-19)
* Rilascio iniziale
