<?php
/**
 * AJ Wills & Estate Planning — Guide Access Gate Endpoint
 * Captures Name, Email, Phone (optional) before unlocking any guide page.
 * A single submission unlocks all guides for the visitor (see guide-gate.js).
 */

declare(strict_types=1);

require_once __DIR__ . '/env.php';
$env = load_env(__DIR__ . '/../.env');

define('RECIPIENT_EMAIL', $env['LEAD_RECIPIENT_EMAIL'] ?? 'info@ajwills.uk');
define('FROM_EMAIL',      'noreply@ajwills.uk');
define('FROM_NAME',       'AJ Wills & Estate Planning');

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

$name  = sanitize($_POST['name']  ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$phone = sanitize($_POST['phone'] ?? '');
$guide = sanitize($_POST['guide'] ?? 'Unknown guide');
$receivedAt = date('d/m/Y H:i:s');
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

if (empty($name) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Please provide your name and a valid email address.', 422);
}

// Log to CSV
$logFile = __DIR__ . '/guide-access.csv';
if (!file_exists($logFile)) {
    file_put_contents($logFile, '"Date","Name","Email","Phone","Guide","IP"' . "\n", FILE_APPEND | LOCK_EX);
}
$row = implode(',', array_map(fn($v) => '"' . str_replace('"', '""', $v) . '"', [
    $receivedAt, $name, $email, $phone, $guide, $ip
])) . "\n";
file_put_contents($logFile, $row, FILE_APPEND | LOCK_EX);

// Notify team
$html = <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><style>
  body{font-family:Arial,sans-serif;color:#2D2D2D;background:#F3F1EF;margin:0;padding:20px}
  .w{max-width:560px;margin:0 auto;background:#fff;border-radius:8px;overflow:hidden}
  .hd{background:linear-gradient(135deg,#373729,#26261B);color:#fff;padding:22px 26px}
  .bd{padding:26px}.field{margin-bottom:14px}
  .lbl{font-size:.72rem;font-weight:700;text-transform:uppercase;color:#767671}
  .val{font-size:.95rem;color:#2D2D2D}
</style></head><body>
<div class="w">
  <div class="hd"><h1 style="margin:0;font-size:1.15rem">New Guide Access</h1><p style="margin:4px 0 0;color:rgba(255,255,255,.65);font-size:.8rem">{$receivedAt}</p></div>
  <div class="bd">
    <div class="field"><div class="lbl">Name</div><div class="val">{$name}</div></div>
    <div class="field"><div class="lbl">Email</div><div class="val"><a href="mailto:{$email}">{$email}</a></div></div>
    <div class="field"><div class="lbl">Phone</div><div class="val">{$phone}</div></div>
    <div class="field"><div class="lbl">Guide</div><div class="val">{$guide}</div></div>
  </div>
</div>
</body></html>
HTML;

$headers  = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\n";
$headers .= "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\nReply-To: {$name} <{$email}>\r\n";
@mail(RECIPIENT_EMAIL, "New Guide Access: {$name}", $html, $headers);

respond(true, 'Thank you! This guide (and all our other guides) is now unlocked for you.');
