<?php
/**
 * AJ Wills & Estate Planning — Contact / Consultation Request Mailer
 * Uses PHPMailer with SMTP (recommended) or PHP mail() fallback.
 *
 * SETUP INSTRUCTIONS:
 * 1. Install PHPMailer via Composer:  composer require phpmailer/phpmailer
 * 2. Set your SMTP credentials in the CONFIGURATION section below.
 * 3. Upload to your server alongside the vendor/ directory.
 */

declare(strict_types=1);

// ── Configuration ──────────────────────────────────────────────────────────
require_once __DIR__ . '/env.php';
$env = load_env(__DIR__ . '/../.env');

define('RECIPIENT_EMAIL', $env['LEAD_RECIPIENT_EMAIL'] ?? 'info@ajwills.uk');
define('RECIPIENT_NAME',  'AJ Wills & Estate Planning');
define('FROM_EMAIL',      'noreply@ajwills.uk');
define('FROM_NAME',       'AJ Wills Website');
define('SITE_NAME',       'AJ Wills & Estate Planning');
define('SITE_URL',        'https://ajwills.uk');

// SMTP Settings (e.g. your hosting SMTP, SendGrid, Mailgun)
define('SMTP_HOST',     'smtp.example.com');   // Change to your SMTP host
define('SMTP_PORT',     587);
define('SMTP_USER',     'your-smtp-username');
define('SMTP_PASSWORD', 'your-smtp-password');
define('SMTP_SECURE',   'tls');

define('RATE_LIMIT', 5); // max submissions per IP per hour
define('CSRF_LIFETIME', 3600);
// ──────────────────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['success' => false, 'message' => 'Method not allowed.']));
}

function respond(bool $success, string $message, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// ── Rate Limiting ──────────────────────────────────────────────────────────
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rateKey = 'rate_' . md5($ip);
if (!isset($_SESSION[$rateKey])) {
    $_SESSION[$rateKey] = ['count' => 0, 'window_start' => time()];
}
if (time() - $_SESSION[$rateKey]['window_start'] > 3600) {
    $_SESSION[$rateKey] = ['count' => 0, 'window_start' => time()];
}
$_SESSION[$rateKey]['count']++;
if ($_SESSION[$rateKey]['count'] > RATE_LIMIT) {
    respond(false, 'Too many submissions. Please try again later or call us directly.', 429);
}

// ── Honeypot ───────────────────────────────────────────────────────────────
if (!empty($_POST['website_url'])) {
    respond(true, 'Thank you for your enquiry. We will be in touch shortly.');
}

// ── Input Validation ───────────────────────────────────────────────────────
$errors = [];

$firstName  = sanitize($_POST['first_name'] ?? '');
$lastName   = sanitize($_POST['last_name']  ?? '');
$email      = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone      = sanitize($_POST['phone']       ?? '');
$service    = sanitize($_POST['service']     ?? '');
$message    = sanitize($_POST['message']     ?? '');
$privacyAgreed = ($_POST['privacy_consent'] ?? '') === 'yes';

if (empty($firstName))                          $errors[] = 'First name is required.';
if (empty($lastName))                           $errors[] = 'Last name is required.';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email address is required.';
if (empty($phone))                              $errors[] = 'Phone number is required.';
if (empty($service))                            $errors[] = 'Please select the service you are enquiring about.';
if (!$privacyAgreed)                            $errors[] = 'You must agree to the Privacy Policy.';

if (!empty($errors)) {
    respond(false, implode(' ', $errors), 422);
}

// ── Build Email HTML ───────────────────────────────────────────────────────
$fullName = trim($firstName . ' ' . $lastName);
$submittedAt = date('d/m/Y H:i:s');

$emailHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
  body { font-family: Arial, sans-serif; color: #2D2D2D; background: #F3F1EF; margin: 0; padding: 20px; }
  .wrapper { max-width: 600px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
  .header { background: linear-gradient(135deg,#373729,#26261B); color: #fff; padding: 28px 32px; }
  .header h1 { margin: 0; font-size: 1.3rem; }
  .header p { margin: 4px 0 0; color: rgba(255,255,255,0.7); font-size: 0.85rem; }
  .badge { display: inline-block; background:#903D13;color:#fff; font-size: 0.72rem; font-weight: bold; padding: 3px 10px; border-radius: 20px; margin-bottom: 12px; text-transform: uppercase; }
  .body { padding: 32px; }
  .field { margin-bottom: 18px; border-bottom: 1px solid #E7E3E0; padding-bottom: 14px; }
  .field:last-child { border-bottom: none; }
  .label { font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #767671; margin-bottom: 4px; }
  .value { font-size: 0.95rem; color: #2D2D2D; }
  .highlight { background: #FAF1EB; border-radius: 4px; padding: 12px 16px; margin: 4px 0; }
  .footer { background: #F3F1EF; padding: 18px 32px; font-size: 0.75rem; color: #767671; border-top: 1px solid #E7E3E0; }
  .btn { display: inline-block; background:#903D13;color:#fff; padding: 10px 22px; border-radius: 20px; font-weight: bold; font-size: 0.875rem; text-decoration: none; margin-top: 12px; }
</style>
</head>
<body>
<div class="wrapper">
  <div class="header">
    <div class="badge">New Enquiry</div>
    <h1>New Consultation Request — AJ Wills</h1>
    <p>Received {$submittedAt}</p>
  </div>
  <div class="body">
    <div class="field"><div class="label">Name</div><div class="value"><strong>{$fullName}</strong></div></div>
    <div class="field"><div class="label">Email</div><div class="value"><a href="mailto:{$email}">{$email}</a></div></div>
    <div class="field"><div class="label">Phone</div><div class="value"><a href="tel:{$phone}">{$phone}</a></div></div>
    <div class="field"><div class="label">Service Enquired About</div><div class="value highlight"><strong>{$service}</strong></div></div>
    <div class="field"><div class="label">Message</div><div class="value" style="white-space:pre-line">{$message}</div></div>
    <a class="btn" href="mailto:{$email}?subject=Re: Your enquiry — AJ Wills &amp; Estate Planning">Reply to {$firstName}</a>
  </div>
  <div class="footer">
    This enquiry was submitted via the AJ Wills website contact form. IP: {$ip}. Time: {$submittedAt}.
  </div>
</div>
</body>
</html>
HTML;

$autoReplyHtml = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><style>
  body { font-family: Arial, sans-serif; color: #2D2D2D; background: #F3F1EF; margin: 0; padding: 20px; }
  .wrapper { max-width: 580px; margin: 0 auto; background: #fff; border-radius: 8px; overflow: hidden; }
  .header { background: linear-gradient(135deg,#373729,#26261B); color: #fff; padding: 28px 32px; }
  .body { padding: 32px; line-height: 1.7; }
  .footer { background: #F3F1EF; padding: 18px 32px; font-size: 0.75rem; color: #767671; border-top: 1px solid #E7E3E0; }
  .btn { display: inline-block; background:#903D13;color:#fff; padding: 12px 24px; border-radius: 20px; font-weight: bold; font-size: 0.9rem; text-decoration: none; margin-top: 16px; }
</style></head>
<body>
<div class="wrapper">
  <div class="header">
    <p style="margin:0 0 8px;color:rgba(255,255,255,0.65);font-size:0.8rem;text-transform:uppercase;letter-spacing:0.08em">Enquiry Confirmation</p>
    <h1 style="margin:0;font-size:1.4rem">Thank You, {$firstName}</h1>
  </div>
  <div class="body">
    <p>Thank you for contacting AJ Wills &amp; Estate Planning. We have received your enquiry and will be in touch within one business day.</p>
    <p><strong>Service enquired about:</strong> {$service}</p>
    <p>In the meantime, if your matter is urgent or you have any questions, please don't hesitate to contact us directly:</p>
    <ul style="margin:12px 0;padding-left:20px">
      <li>📞 <a href="tel:+441234567890">01234 567 890</a> (placeholder — Mon–Fri, 9am–5pm)</li>
      <li>✉ <a href="mailto:info@ajwills.uk">info@ajwills.uk</a></li>
    </ul>
    <p>We look forward to helping you protect what matters most.</p>
    <p>Warm regards,<br><strong>The AJ Wills Team</strong></p>
    <a class="btn" href="https://ajwills.uk">Visit Our Website</a>
  </div>
  <div class="footer">
    AJ Wills &amp; Estate Planning | [Registered Office Address], United Kingdom<br>
    This email was sent in response to your enquiry submitted at ajwills.uk. If this was not you, please disregard this email.
  </div>
</div>
</body>
</html>
HTML;

// ── Send Emails ────────────────────────────────────────────────────────────
$sent = false;

$phpmailerPath = __DIR__ . '/../vendor/autoload.php';
if (file_exists($phpmailerPath)) {
    require_once $phpmailerPath;

    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = SMTP_SECURE;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress(RECIPIENT_EMAIL, RECIPIENT_NAME);
        $mail->addReplyTo($email, $fullName);
        $mail->Subject = "New Enquiry: {$service} — {$fullName}";
        $mail->isHTML(true);
        $mail->Body    = $emailHtml;
        $mail->AltBody = "New enquiry from {$fullName}. Service: {$service}. Email: {$email}. Phone: {$phone}. Message: {$message}";
        $mail->send();

        $mail2 = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail2->isSMTP();
        $mail2->Host       = SMTP_HOST;
        $mail2->SMTPAuth   = true;
        $mail2->Username   = SMTP_USER;
        $mail2->Password   = SMTP_PASSWORD;
        $mail2->SMTPSecure = SMTP_SECURE;
        $mail2->Port       = SMTP_PORT;
        $mail2->CharSet    = 'UTF-8';

        $mail2->setFrom(FROM_EMAIL, FROM_NAME);
        $mail2->addAddress($email, $fullName);
        $mail2->Subject  = "Your AJ Wills enquiry — we'll be in touch shortly";
        $mail2->isHTML(true);
        $mail2->Body     = $autoReplyHtml;
        $mail2->AltBody  = "Dear {$firstName}, thank you for your enquiry. We will be in touch within one business day.";
        $mail2->send();

        $sent = true;
    } catch (\PHPMailer\PHPMailer\Exception $e) {
        error_log('AJ Wills PHPMailer error: ' . $e->getMessage());
    }
}

if (!$sent) {
    $headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\nReply-To: {$fullName} <{$email}>\r\n";
    $sent = mail(RECIPIENT_EMAIL, "New Enquiry: {$service} — {$fullName}", $emailHtml, $headers);

    $headers2  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
    $headers2 .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
    mail($email, "Your AJ Wills enquiry — we'll be in touch shortly", $autoReplyHtml, $headers2);
}

// ── Log Enquiry ────────────────────────────────────────────────────────────
$logFile = __DIR__ . '/enquiry-log.csv';
$logLine = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', [
    $submittedAt, $fullName, $email, $phone, $service, str_replace("\n", ' ', $message)
])) . "\n";

if (!file_exists($logFile)) {
    file_put_contents($logFile, '"Date","Name","Email","Phone","Service","Message"' . "\n", FILE_APPEND | LOCK_EX);
}
file_put_contents($logFile, $logLine, FILE_APPEND | LOCK_EX);

// ── Respond ────────────────────────────────────────────────────────────────
if ($sent) {
    respond(true, 'Thank you for your enquiry. We will be in touch within one business day.');
} else {
    error_log("AJ Wills: mail send failed for {$email} at {$submittedAt}");
    respond(false, 'We were unable to send your message at this time. Please call us directly on 01234 567 890 or email info@ajwills.uk.', 500);
}
