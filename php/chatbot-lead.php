<?php
/**
 * AJ Wills & Estate Planning — AJ Assistant Chatbot Lead Capture Endpoint
 * Receives lead data from the AJ Assistant chatbot widget and emails the team.
 *
 * SETUP: set RECIPIENT_EMAIL and (optionally) SMTP settings below before going live.
 */

declare(strict_types=1);

define('RECIPIENT_EMAIL', 'info@ajwills.uk');
define('RECIPIENT_NAME',  'AJ Wills & Estate Planning');
define('FROM_EMAIL',      'noreply@ajwills.uk');
define('FROM_NAME',       'AJ Wills — AJ Assistant');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false]));
}

function sanitize(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

function respond(bool $s, string $m, int $c = 200): void {
    http_response_code($c);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $s, 'message' => $m]);
    exit;
}

// Honeypot
if (!empty($_POST['website_url'])) respond(true, 'OK');

$name       = sanitize($_POST['name']      ?? '');
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone      = sanitize($_POST['phone']     ?? '');
$service    = sanitize($_POST['service']   ?? '');
$bestTime   = sanitize($_POST['best_time'] ?? '');
$consent    = sanitize($_POST['consent']   ?? '');
$source     = sanitize($_POST['source']    ?? 'chatbot');
$receivedAt = date('d/m/Y H:i:s');
$ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL) || $consent !== 'Yes') {
    respond(false, 'Name, a valid email and consent to be contacted are required.', 422);
}

$html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><style>
  body{font-family:Arial,sans-serif;color:#2D2D2D;background:#F3F1EF;margin:0;padding:20px}
  .w{max-width:580px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden}
  .hd{background:linear-gradient(135deg,#373729,#26261B);color:#fff;padding:24px 28px}
  .bd{padding:28px}.field{margin-bottom:16px;border-bottom:1px solid #E7E3E0;padding-bottom:12px}
  .field:last-child{border-bottom:none}.lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;letter-spacing:.08em;color:#767671;margin-bottom:4px}
  .val{font-size:.95rem;color:#2D2D2D}.hl{background:#FAF1EB;border-radius:4px;padding:10px 14px;margin:4px 0}
  .ft{background:#F3F1EF;padding:16px 28px;font-size:.72rem;color:#767671;border-top:1px solid #E7E3E0}
  .btn{display:inline-block;background:#903D13;color:#fff;padding:10px 20px;border-radius:20px;font-weight:bold;font-size:.875rem;text-decoration:none;margin-top:10px}
  .badge{display:inline-block;background:#903D13;color:#fff;font-size:.7rem;font-weight:bold;padding:3px 10px;border-radius:20px;text-transform:uppercase;margin-bottom:10px}
</style></head>
<body>
<div class="w">
  <div class="hd"><div class="badge">AJ Assistant Lead</div><h1 style="margin:0;font-size:1.25rem">New Chatbot Lead</h1><p style="margin:4px 0 0;color:rgba(255,255,255,.65);font-size:.82rem">{$receivedAt}</p></div>
  <div class="bd">
    <div class="field"><div class="lbl">Name</div><div class="val"><strong>{$name}</strong></div></div>
    <div class="field"><div class="lbl">Email</div><div class="val"><a href="mailto:{$email}">{$email}</a></div></div>
    <div class="field"><div class="lbl">Phone</div><div class="val"><a href="tel:{$phone}">{$phone}</a></div></div>
    <div class="field"><div class="lbl">Service Required</div><div class="val hl"><strong>{$service}</strong></div></div>
    <div class="field"><div class="lbl">Best Time to Contact</div><div class="val">{$bestTime}</div></div>
    <div class="field"><div class="lbl">Consent to Contact</div><div class="val">{$consent}</div></div>
    <a class="btn" href="mailto:{$email}?subject=Re: Your AJ Wills enquiry — {$service}">Reply to {$name}</a>
  </div>
  <div class="ft">Source: {$source} | IP: {$ip} | {$receivedAt}<br>AJ Wills &amp; Estate Planning — this enquiry did not receive legal advice from the chatbot.</div>
</div>
</body>
</html>
HTML;

// Log to CSV (ensure this directory is not web-accessible in production)
$logFile = __DIR__ . '/chatbot-leads.csv';
if (!file_exists($logFile)) {
    file_put_contents($logFile, '"Date","Name","Email","Phone","Service","Best Time","Consent","Source"' . "\n", FILE_APPEND | LOCK_EX);
}
$row = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', [
    $receivedAt, $name, $email, $phone, $service, $bestTime, $consent, $source
])) . "\n";
file_put_contents($logFile, $row, FILE_APPEND | LOCK_EX);

// PHPMailer if available
$phpmailerPath = __DIR__ . '/../vendor/autoload.php';
$sent = false;
if (file_exists($phpmailerPath)) {
    require_once $phpmailerPath;
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = defined('SMTP_HOST')     ? SMTP_HOST     : 'localhost';
        $mail->SMTPAuth   = true;
        $mail->Username   = defined('SMTP_USER')     ? SMTP_USER     : '';
        $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
        $mail->SMTPSecure = defined('SMTP_SECURE')   ? SMTP_SECURE   : 'tls';
        $mail->Port       = defined('SMTP_PORT')     ? SMTP_PORT     : 587;
        $mail->CharSet    = 'UTF-8';
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress(RECIPIENT_EMAIL, RECIPIENT_NAME);
        $mail->addReplyTo($email, $name);
        $mail->Subject = "Chatbot Lead: {$service} — {$name}";
        $mail->isHTML(true);
        $mail->Body    = $html;
        $mail->AltBody = "New chatbot lead. Name: {$name}. Email: {$email}. Phone: {$phone}. Service: {$service}. Best time: {$bestTime}.";
        $mail->send();
        $sent = true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('AJ Wills chatbot lead mailer error: ' . $e->getMessage());
    }
}

if (!$sent) {
    $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\nReply-To: {$name} <{$email}>\r\n";
    $sent = mail(RECIPIENT_EMAIL, "Chatbot Lead: {$service} — {$name}", $html, $headers);
}

respond($sent, $sent ? 'Lead captured — thank you!' : 'Submission logged, but email failed. Please call us directly.', $sent ? 200 : 500);
