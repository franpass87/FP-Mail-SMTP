<?php
/**
 * Configuratore SMTP per PHPMailer.
 *
 * Legge le opzioni del plugin e configura PHPMailer via phpmailer_init.
 *
 * @package FP\Fpmail\Mail
 */

declare(strict_types=1);

namespace FP\Fpmail\Mail;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Configura PHPMailer con SMTP e opzioni from.
 */
final class SmtpConfigurator
{
    /**
     * Registra gli hook.
     */
    public function register(): void
    {
        add_action('phpmailer_init', [$this, 'configurePhpmailer'], 5);
        add_filter('wp_mail_from', [$this, 'filterFromEmail'], 10);
        add_filter('wp_mail_from_name', [$this, 'filterFromName'], 10);
    }

    /**
     * Configura PHPMailer per SMTP.
     *
     * @param PHPMailer $phpmailer Istanza PHPMailer (passata per riferimento).
     */
    public function configurePhpmailer(PHPMailer $phpmailer): void
    {
        $host = get_option('fp_fpmail_smtp_host', '');
        if ($host === '') {
            return;
        }

        $phpmailer->isSMTP();
        $phpmailer->Host = $host;
        $phpmailer->Port = absint(get_option('fp_fpmail_smtp_port', 587));
        $phpmailer->SMTPAuth = !empty(get_option('fp_fpmail_smtp_user', ''));

        $user = get_option('fp_fpmail_smtp_user', '');
        if ($user !== '') {
            $phpmailer->Username = $user;
            $pass = get_option('fp_fpmail_smtp_pass', '');
            $phpmailer->Password = $this->decryptPassword($pass);
        }

        $encryption = get_option('fp_fpmail_smtp_encryption', 'tls');
        if ($encryption === 'ssl') {
            $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $phpmailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $phpmailer->SMTPSecure = '';
            $phpmailer->SMTPAutoTLS = false;
        }
    }

    /**
     * Override from email se configurato.
     *
     * @param string $email Email di default.
     * @return string
     */
    public function filterFromEmail(string $email): string
    {
        $from = get_option('fp_fpmail_from_email', '');
        if ($from !== '' && is_email($from)) {
            return $from;
        }
        return $email;
    }

    /**
     * Override from name se configurato.
     *
     * @param string $name Nome di default.
     * @return string
     */
    public function filterFromName(string $name): string
    {
        $fromName = get_option('fp_fpmail_from_name', '');
        if ($fromName !== '') {
            return $fromName;
        }
        return $name;
    }

    /**
     * Decripta la password salvata (base64 per evitare plaintext).
     *
     * @param string $encrypted Password criptata.
     * @return string
     */
    private function decryptPassword(string $encrypted): string
    {
        if ($encrypted === '') {
            return '';
        }
        $decoded = base64_decode($encrypted, true);
        return $decoded !== false ? $decoded : $encrypted;
    }
}
