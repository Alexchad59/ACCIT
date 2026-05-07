<?php
/**
 * ACCIT — Traitement du formulaire de contact
 * Reçoit un POST JSON, valide, envoie l'e-mail via SMTP OVH, enregistre en BDD.
 */

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// ── Sécurité : POST uniquement ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Méthode non autorisée.']));
}

// ── Chargement config ────────────────────────────────────
$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    exit(json_encode(['success' => false, 'message' => 'Configuration serveur manquante.']));
}
require_once $configFile;

// ── Lecture du corps de la requête ───────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

// Fallback form classique
if (!$data) {
    $data = $_POST;
}

// ── Validation ───────────────────────────────────────────
$errors = [];

$prenom    = trim($data['prenom']    ?? '');
$nom       = trim($data['nom']       ?? '');
$email     = trim($data['email']     ?? '');
$telephone = trim($data['telephone'] ?? '');
$societe   = trim($data['societe']   ?? '');
$sujet     = trim($data['sujet']     ?? '');
$message   = trim($data['message']   ?? '');
$rgpd      = !empty($data['rgpd']);
$honeypot  = trim($data['website']   ?? ''); // champ anti-spam

// Anti-spam honeypot
if ($honeypot !== '') {
    exit(json_encode(['success' => true, 'message' => 'Message envoyé.']));
}

if (strlen($prenom)  < 2)  $errors[] = 'Prénom invalide.';
if (strlen($nom)     < 2)  $errors[] = 'Nom invalide.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Adresse e-mail invalide.';
if (strlen($societe) < 2)  $errors[] = 'Société invalide.';
if (strlen($sujet)   < 2)  $errors[] = 'Sujet invalide.';
if (strlen($message) < 10) $errors[] = 'Message trop court.';
if (!$rgpd)                $errors[] = 'Vous devez accepter la politique de confidentialité.';

if ($errors) {
    http_response_code(422);
    exit(json_encode(['success' => false, 'message' => implode(' ', $errors)]));
}

// ── Nettoyage ────────────────────────────────────────────
$clean = fn(string $s): string => htmlspecialchars(strip_tags($s), ENT_QUOTES, 'UTF-8');

$prenom    = $clean($prenom);
$nom       = $clean($nom);
$email     = filter_var($email, FILTER_SANITIZE_EMAIL);
$telephone = $clean($telephone);
$societe   = $clean($societe);
$sujet     = $clean($sujet);
$message   = $clean($message);
$ip        = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

// ── Enregistrement en base ───────────────────────────────
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false]
    );

    $stmt = $pdo->prepare("
        INSERT INTO contacts (prenom, nom, email, telephone, societe, sujet, message, ip)
        VALUES (:prenom, :nom, :email, :telephone, :societe, :sujet, :message, :ip)
    ");
    $stmt->execute([
        ':prenom'    => $prenom,
        ':nom'       => $nom,
        ':email'     => $email,
        ':telephone' => $telephone,
        ':societe'   => $societe,
        ':sujet'     => $sujet,
        ':message'   => $message,
        ':ip'        => $ip,
    ]);

} catch (PDOException $e) {
    // On log l'erreur mais on ne bloque pas l'envoi du mail
    error_log('[ACCIT] BDD erreur : ' . $e->getMessage());
}

// ── Envoi e-mail via PHPMailer ───────────────────────────
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Corps de l'e-mail de notification (pour Alexandre)
$sujetLabels = [
    'echange' => 'Échange sur mon informatique',
    'dsi'     => 'DSI externalisée',
    'conseil' => 'Conseils',
    'devis'   => 'Devis',
    'autre'   => 'Autre',
];
$sujetLabel = $sujetLabels[$sujet] ?? $sujet;
$dateStr    = (new DateTime())->format('d/m/Y à H:i');

$htmlNotif = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:Inter,Arial,sans-serif;background:#F1F5F9;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F1F5F9;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
        <!-- Header -->
        <tr>
          <td style="background:#071729;padding:28px 36px;">
            <p style="margin:0;font-size:20px;font-weight:800;color:#ffffff;letter-spacing:-.02em;">ACCIT</p>
            <p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,.5);">Nouveau contact depuis accit.fr</p>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:32px 36px;">
            <p style="margin:0 0 24px;font-size:15px;color:#334155;">
              Vous avez reçu un message le <strong>{$dateStr}</strong>.
            </p>
            <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
              <tr style="border-bottom:1px solid #E2E8F0;">
                <td style="padding:10px 0;width:130px;font-size:13px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.04em;">Prénom</td>
                <td style="padding:10px 0;font-size:15px;color:#0F172A;">{$prenom}</td>
              </tr>
              <tr style="border-bottom:1px solid #E2E8F0;">
                <td style="padding:10px 0;font-size:13px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.04em;">Nom</td>
                <td style="padding:10px 0;font-size:15px;color:#0F172A;">{$nom}</td>
              </tr>
              <tr style="border-bottom:1px solid #E2E8F0;">
                <td style="padding:10px 0;font-size:13px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.04em;">E-mail</td>
                <td style="padding:10px 0;font-size:15px;color:#1B4FD8;"><a href="mailto:{$email}" style="color:#1B4FD8;">{$email}</a></td>
              </tr>
              <tr style="border-bottom:1px solid #E2E8F0;">
                <td style="padding:10px 0;font-size:13px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.04em;">Téléphone</td>
                <td style="padding:10px 0;font-size:15px;color:#0F172A;">{$telephone}</td>
              </tr>
              <tr style="border-bottom:1px solid #E2E8F0;">
                <td style="padding:10px 0;font-size:13px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.04em;">Société</td>
                <td style="padding:10px 0;font-size:15px;color:#0F172A;">{$societe}</td>
              </tr>
              <tr style="border-bottom:1px solid #E2E8F0;">
                <td style="padding:10px 0;font-size:13px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.04em;">Sujet</td>
                <td style="padding:10px 0;font-size:15px;color:#0F172A;">{$sujetLabel}</td>
              </tr>
              <tr>
                <td colspan="2" style="padding:16px 0 0;">
                  <p style="margin:0 0 8px;font-size:13px;font-weight:600;color:#64748B;text-transform:uppercase;letter-spacing:.04em;">Message</p>
                  <p style="margin:0;font-size:15px;color:#334155;line-height:1.7;white-space:pre-wrap;">{$message}</p>
                </td>
              </tr>
            </table>
            <div style="margin-top:28px;padding-top:24px;border-top:1px solid #E2E8F0;">
              <a href="mailto:{$email}" style="display:inline-block;background:#1B4FD8;color:#ffffff;text-decoration:none;padding:12px 24px;border-radius:8px;font-size:14px;font-weight:600;">Répondre à {$prenom}</a>
            </div>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="padding:20px 36px;background:#F8FAFC;border-top:1px solid #E2E8F0;">
            <p style="margin:0;font-size:12px;color:#94A3B8;">Ce message a été envoyé depuis le formulaire de contact de <a href="https://accit.fr" style="color:#1B4FD8;">accit.fr</a>. IP : {$ip}</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

// Corps de l'e-mail de confirmation (pour le visiteur)
$htmlConfirm = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;font-family:Inter,Arial,sans-serif;background:#F1F5F9;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#F1F5F9;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
        <tr>
          <td style="background:#071729;padding:28px 36px;">
            <p style="margin:0;font-size:20px;font-weight:800;color:#ffffff;letter-spacing:-.02em;">ACCIT</p>
            <p style="margin:4px 0 0;font-size:13px;color:rgba(255,255,255,.5);">Conseil en systèmes d'information</p>
          </td>
        </tr>
        <tr>
          <td style="padding:36px 36px 28px;">
            <h1 style="margin:0 0 16px;font-size:22px;font-weight:800;color:#071729;">Bonjour {$prenom},</h1>
            <p style="margin:0 0 20px;font-size:15px;color:#334155;line-height:1.7;">
              Merci pour votre message. Je l'ai bien reçu et je reviendrai vers vous <strong>sous 24h ouvrées</strong>.
            </p>
            <p style="margin:0 0 28px;font-size:15px;color:#334155;line-height:1.7;">
              En attendant, si votre besoin est urgent, n'hésitez pas à m'appeler directement :
            </p>
            <div style="background:#EFF6FF;border:1px solid #DBEAFE;border-radius:10px;padding:20px 24px;margin-bottom:28px;">
              <p style="margin:0;font-size:16px;font-weight:700;color:#071729;">📞 06 79 42 66 21</p>
              <p style="margin:4px 0 0;font-size:13px;color:#64748B;">Lun–Ven 9h00–18h00</p>
            </div>
            <p style="margin:0;font-size:14px;color:#64748B;line-height:1.7;">
              Bien cordialement,<br>
              <strong style="color:#071729;">Alexandre Chadenat</strong><br>
              Fondateur — ACCIT
            </p>
          </td>
        </tr>
        <tr>
          <td style="padding:20px 36px;background:#F8FAFC;border-top:1px solid #E2E8F0;">
            <p style="margin:0;font-size:12px;color:#94A3B8;">
              ACCIT · 70 rue du Cheval Blanc, 59700 Marcq-en-Barœul ·
              <a href="https://accit.fr" style="color:#1B4FD8;">accit.fr</a>
            </p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>
HTML;

try {
    // ─ E-mail 1 : notification à Alexandre ─
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = SMTP_HOST;
    $mail->SMTPAuth   = true;
    $mail->Username   = SMTP_USER;
    $mail->Password   = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = SMTP_PORT;
    $mail->CharSet    = 'UTF-8';

    $mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail->addAddress(MAIL_TO, 'Alexandre Chadenat');
    $mail->addReplyTo($email, "$prenom $nom");
    $mail->Subject = "[ACCIT] Nouveau contact : $prenom $nom — $sujetLabel";
    $mail->isHTML(true);
    $mail->Body    = $htmlNotif;
    $mail->AltBody = "Nouveau contact de $prenom $nom ($email) — $sujetLabel\n\n$message";
    $mail->send();

    // ─ E-mail 2 : confirmation au visiteur ─
    $mail2 = new PHPMailer(true);
    $mail2->isSMTP();
    $mail2->Host       = SMTP_HOST;
    $mail2->SMTPAuth   = true;
    $mail2->Username   = SMTP_USER;
    $mail2->Password   = SMTP_PASS;
    $mail2->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail2->Port       = SMTP_PORT;
    $mail2->CharSet    = 'UTF-8';

    $mail2->setFrom(MAIL_FROM, MAIL_FROM_NAME);
    $mail2->addAddress($email, "$prenom $nom");
    $mail2->Subject = "Votre message a bien été reçu — ACCIT";
    $mail2->isHTML(true);
    $mail2->Body    = $htmlConfirm;
    $mail2->AltBody = "Bonjour $prenom, merci pour votre message. Je reviendrai vers vous sous 24h. — Alexandre Chadenat, ACCIT";
    $mail2->send();

    echo json_encode(['success' => true, 'message' => 'Votre message a bien été envoyé. Je vous réponds sous 24h.']);

} catch (Exception $e) {
    error_log('[ACCIT] Mail erreur : ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Erreur lors de l\'envoi. Veuillez réessayer ou nous appeler directement.']);
}
