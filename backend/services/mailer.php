<?php
// ══════════════════════════════════════════════════════════════════════════════
//  backend/services/mailer.php — Supinfo.TV
//  Envoi d'e-mails via SMTP natif PHP (sans dépendance externe).
//  Supporte : vérification e-mail, reset de mot de passe.
// ══════════════════════════════════════════════════════════════════════════════

require_once __DIR__ . '/../config/mail.php';

/**
 * Envoie un e-mail via SMTP (socket PHP natif, STARTTLS).
 * Pas de dépendance Composer requise.
 *
 * @param  string $to       Destinataire
 * @param  string $subject  Sujet
 * @param  string $htmlBody Corps HTML
 * @return array  ['ok' => bool, 'error' => string|null]
 */
function mailer_send(string $to, string $subject, string $htmlBody): array
{
    // En dev sans config SMTP, on log et on retourne ok pour ne pas bloquer
    if (empty(MAIL_USER) || empty(MAIL_PASS)) {
        error_log("[MAILER] Simulated send to {$to} — subject: {$subject}");
        return ['ok' => true, 'error' => null];
    }

    $boundary = '----=_Part_' . md5(uniqid('', true));
    $textBody = strip_tags($htmlBody);

    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'From: ' . MAIL_FROM_NAME . ' <' . MAIL_FROM . '>',
        'To: ' . $to,
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'X-Mailer: Supinfo.TV/1.0',
        'Date: ' . date('r'),
    ]);

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($textBody)) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($htmlBody)) . "\r\n";
    $body .= "--{$boundary}--";

    try {
        $smtp = _mailer_smtp_connect();
        if (!$smtp) {
            return ['ok' => false, 'error' => 'Impossible de se connecter au serveur SMTP.'];
        }

        _mailer_smtp_cmd($smtp, 'EHLO ' . gethostname());
        _mailer_smtp_starttls($smtp);
        _mailer_smtp_cmd($smtp, 'AUTH LOGIN');
        _mailer_smtp_cmd($smtp, base64_encode(MAIL_USER));
        _mailer_smtp_cmd($smtp, base64_encode(MAIL_PASS));
        _mailer_smtp_cmd($smtp, 'MAIL FROM:<' . MAIL_FROM . '>');
        _mailer_smtp_cmd($smtp, 'RCPT TO:<' . $to . '>');
        _mailer_smtp_cmd($smtp, 'DATA');
        fwrite($smtp, $headers . "\r\n\r\n" . $body . "\r\n.\r\n");
        _mailer_smtp_read($smtp);
        _mailer_smtp_cmd($smtp, 'QUIT');
        fclose($smtp);

        return ['ok' => true, 'error' => null];

    } catch (Throwable $e) {
        error_log('[MAILER] Erreur : ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Erreur lors de l\'envoi de l\'e-mail.'];
    }
}

/** @internal */
function _mailer_smtp_connect()
{
    $ctx = stream_context_create(['ssl' => [
        'verify_peer'       => false, // En prod, mettre true
        'verify_peer_name'  => false,
        'allow_self_signed' => true,
    ]]);

    $smtp = stream_socket_client(
        'tcp://' . MAIL_HOST . ':' . MAIL_PORT,
        $errno, $errstr, 10,
        STREAM_CLIENT_CONNECT, $ctx
    );

    if (!$smtp) {
        error_log("[MAILER] Connexion SMTP échouée : {$errstr} ({$errno})");
        return null;
    }

    stream_set_timeout($smtp, 10);
    _mailer_smtp_read($smtp); // Lire le greeting 220
    return $smtp;
}

/** @internal */
function _mailer_smtp_starttls($smtp): void
{
    if (MAIL_ENCRYPTION === 'tls') {
        fwrite($smtp, "STARTTLS\r\n");
        _mailer_smtp_read($smtp);
        stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        fwrite($smtp, "EHLO " . gethostname() . "\r\n");
        _mailer_smtp_read($smtp);
    }
}

/** @internal */
function _mailer_smtp_cmd($smtp, string $cmd): string
{
    fwrite($smtp, $cmd . "\r\n");
    return _mailer_smtp_read($smtp);
}

/** @internal */
function _mailer_smtp_read($smtp): string
{
    $response = '';
    while ($line = fgets($smtp, 512)) {
        $response .= $line;
        if (substr($line, 3, 1) === ' ') break;
    }
    return $response;
}

// ── Templates e-mail ──────────────────────────────────────────────────────────

/**
 * Envoie l'e-mail de vérification d'adresse.
 */
function mailer_send_verification(string $to, string $username, string $token): array
{
    $basePath  = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    $verifyUrl = $basePath . '/backend/pages/verify-email.php?token=' . urlencode($token);

    $subject = 'Vérifiez votre adresse e-mail — Supinfo.TV';
    $html    = mailer_template_base($username, $subject, "
        <p style='font-size:15px;color:#6db8b9;line-height:1.7;margin:0 0 24px;'>
            Merci de vous être inscrit sur <strong style='color:#e8f8f0;'>Supinfo.TV</strong> !
            Cliquez sur le bouton ci-dessous pour vérifier votre adresse e-mail.
        </p>
        <a href='{$verifyUrl}' style='
            display:inline-block;padding:14px 32px;background:#57cc99;
            color:#fff;font-weight:500;font-size:14px;border-radius:8px;
            text-decoration:none;letter-spacing:0.06em;text-transform:uppercase;
        '>Vérifier mon e-mail</a>
        <p style='font-size:12px;color:#3a7a7c;margin:24px 0 0;'>
            Ce lien expire dans <strong>24 heures</strong>. Si vous n'avez pas créé de compte,
            ignorez cet e-mail.
        </p>
    ");

    return mailer_send($to, $subject, $html);
}

/**
 * Envoie l'e-mail de réinitialisation du mot de passe.
 */
function mailer_send_password_reset(string $to, string $username, string $token): array
{
    $basePath = rtrim(getenv('APP_URL') ?: 'http://localhost', '/');
    $resetUrl = $basePath . '/backend/pages/reset-password.php?token=' . urlencode($token);

    $subject = 'Réinitialisation de mot de passe — Supinfo.TV';
    $html    = mailer_template_base($username, $subject, "
        <p style='font-size:15px;color:#6db8b9;line-height:1.7;margin:0 0 24px;'>
            Vous avez demandé une réinitialisation de mot de passe pour votre compte
            <strong style='color:#e8f8f0;'>Supinfo.TV</strong>.
        </p>
        <a href='{$resetUrl}' style='
            display:inline-block;padding:14px 32px;background:#57cc99;
            color:#fff;font-weight:500;font-size:14px;border-radius:8px;
            text-decoration:none;letter-spacing:0.06em;text-transform:uppercase;
        '>Réinitialiser mon mot de passe</a>
        <p style='font-size:12px;color:#3a7a7c;margin:24px 0 0;'>
            Ce lien expire dans <strong>1 heure</strong>. Si vous n'avez pas fait cette demande,
            ignorez cet e-mail — votre mot de passe reste inchangé.
        </p>
    ");

    return mailer_send($to, $subject, $html);
}

/**
 * Template HTML de base pour tous les e-mails Supinfo.TV.
 * @internal
 */
function mailer_template_base(string $username, string $title, string $content): string
{
    $safeUser  = htmlspecialchars($username);
    $safeTitle = htmlspecialchars($title);
    $year      = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>{$safeTitle}</title>
</head>
<body style="margin:0;padding:0;background:#081820;font-family:'Helvetica Neue',Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#081820;padding:40px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="
        background:#0d2535;border:1px solid #1e4d6a;border-radius:12px;
        overflow:hidden;max-width:600px;width:100%;
      ">
        <!-- Header -->
        <tr><td style="padding:32px 40px;border-bottom:1px solid #1e4d6a;text-align:center;">
          <span style="font-size:20px;font-weight:500;letter-spacing:0.07em;color:#57cc99;">
            SUPINFO<span style="color:#6db8b9;">.TV</span>
          </span>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:40px;">
          <h1 style="font-size:22px;font-weight:500;color:#e8f8f0;margin:0 0 8px;letter-spacing:-0.01em;">
            Bonjour, {$safeUser} !
          </h1>
          {$content}
        </td></tr>
        <!-- Footer -->
        <tr><td style="padding:24px 40px;border-top:1px solid #1e4d6a;text-align:center;">
          <p style="font-size:12px;color:#3a7a7c;margin:0;">
            &copy; {$year} Supinfo.TV — Projet académique.<br>
            Données fournies par <a href="https://www.themoviedb.org/" style="color:#57cc99;">TMDB</a>.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;
}
