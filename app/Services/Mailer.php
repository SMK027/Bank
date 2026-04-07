<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;

/**
 * Service d'envoi de mails via SMTP (PHPMailer).
 * Configuration lue depuis les variables d'environnement MAIL_*.
 */
class Mailer
{
    private function make(): PHPMailer
    {
        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->CharSet = 'UTF-8';

        $mail->Host     = getenv('MAIL_HOST') ?: 'mailpit';
        $mail->Port     = (int)(getenv('MAIL_PORT') ?: 1025);
        $mail->SMTPAuth = false;

        $user = getenv('MAIL_USERNAME') ?: '';
        $pass = getenv('MAIL_PASSWORD') ?: '';
        if ($user !== '') {
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $enc = strtolower(getenv('MAIL_ENCRYPTION') ?: '');
            if ($enc === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($enc === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }
        }

        $from     = getenv('MAIL_FROM')      ?: 'noreply@bankapp.local';
        $fromName = getenv('MAIL_FROM_NAME') ?: 'BankApp';
        $mail->setFrom($from, $fromName);

        return $mail;
    }

    /**
     * Envoie le mail de réinitialisation de mot de passe.
     */
    public function sendPasswordReset(string $toEmail, string $toName, string $resetUrl): void
    {
        $mail = $this->make();
        $mail->addAddress($toEmail, $toName);
        $mail->Subject = 'Réinitialisation de votre mot de passe — BankApp';
        $mail->isHTML(true);

        $expiry      = '30 minutes';
        $safeUrl     = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
        $salutation  = $toName !== '' ? ' ' . htmlspecialchars($toName, ENT_QUOTES, 'UTF-8') : '';

        $mail->Body = '<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2563eb;">BankApp — Réinitialisation de mot de passe</h2>
    <p>Bonjour' . $salutation . ',</p>
    <p>Nous avons reçu une demande de réinitialisation du mot de passe associé à votre compte.</p>
    <p>Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe :</p>
    <p style="text-align: center; margin: 30px 0;">
        <a href="' . $safeUrl . '"
           style="background-color: #2563eb; color: #fff; padding: 12px 28px;
                  text-decoration: none; border-radius: 6px; font-size: 16px;">
            Réinitialiser mon mot de passe
        </a>
    </p>
    <p style="color: #666; font-size: 13px;">
        Ce lien est valable <strong>' . $expiry . '</strong> et ne peut être utilisé qu\'une seule fois.<br>
        Si vous n\'êtes pas à l\'origine de cette demande, ignorez simplement ce message.
    </p>
    <hr style="border: none; border-top: 1px solid #eee; margin: 20px 0;">
    <p style="color: #999; font-size: 12px;">
        Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br>
        <a href="' . $safeUrl . '" style="color: #2563eb;">' . $safeUrl . '</a>
    </p>
</body>
</html>';

        $mail->AltBody = "Réinitialisez votre mot de passe BankApp en suivant ce lien :\n{$resetUrl}\n\nCe lien expire dans {$expiry} et ne peut être utilisé qu'une seule fois.\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message.";

        $mail->send();
    }
}
