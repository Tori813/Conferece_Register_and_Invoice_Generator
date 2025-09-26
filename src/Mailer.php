<?php

namespace App;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Mailer
{
    private PHPMailer $mail;

    /**
     * Mailer constructor.
     * Provide SMTP configuration via the $config array.
     * Supported keys: host, port, username, password, encryption (tls|ssl|null), from_email, from_name.
     */
    public function __construct(array $config = [])
    {
        $this->mail = new PHPMailer(true);

        // Basic SMTP setup
        $this->mail->isSMTP();
        $this->mail->Host       = $config['host']       ?? 'smtp.gmail.com';
        $this->mail->SMTPAuth   = true;
        $this->mail->Username   = $config['username']   ?? 'vaokello@gmail.com';
        $this->mail->Password   = $config['password']   ?? 'odizryolxybtsgzf';
        $this->mail->Port       = isset($config['port']) ? (int)$config['port'] : 587;

        $enc = strtolower($config['encryption'] ?? 'tls');
         if ($enc === 'ssl') {
         $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
         } elseif ($enc === 'tls') {
         $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
         } else {
         $this->mail->SMTPSecure = false;
         $this->mail->SMTPAutoTLS = false;
}


        // From
        $fromEmail = $config['from_email'] ?? 'no-reply@example.com';
        $fromName  = $config['from_name']  ?? 'Conference App';
        $this->mail->setFrom($fromEmail, $fromName);

        // Encoding defaults
        $this->mail->CharSet  = 'UTF-8';
        $this->mail->Encoding = 'base64';
    }

    /**
     * Send an email
     *
     * @param string       $toEmail
     * @param string       $toName
     * @param string       $subject
     * @param string       $htmlBody  HTML body
     * @param string|null  $textBody  Optional plain-text alternative
     * @param array        $attachments Array of file paths to attach
     * @return bool
     * @throws Exception
     */
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $htmlBody,
        ?string $textBody = null,
        array $attachments = []
    ): bool {
        // Reset recipients for reuse
        $this->mail->clearAddresses();
        $this->mail->clearAttachments();

        $this->mail->addAddress($toEmail, $toName);
        $this->mail->isHTML(true);
        $this->mail->Subject = $subject;
        $this->mail->Body    = $htmlBody;
        $this->mail->AltBody = $textBody ?? strip_tags(str_replace(['<br>','<br/>','<br />'], "\n", $htmlBody));

        // Add attachments
        foreach ($attachments as $path) {
            if (is_string($path) && is_file($path)) {
                $this->mail->addAttachment($path);
            }
        }

        return $this->mail->send();
    }
}
