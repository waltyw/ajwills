<?php
declare(strict_types=1);

function load_env(string $path): array {
    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $env[trim($key)] = trim($value);
    }
    return $env;
}

$env = load_env(__DIR__ . '/.env');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = strtolower(trim($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $accounts = [
        strtolower($env['IAN_USERNAME'] ?? '') => $env['IAN_PASSWORD'] ?? '',
        strtolower($env['AMY_USERNAME'] ?? '') => $env['AMY_PASSWORD'] ?? '',
    ];

    if (isset($accounts[$username]) && $accounts[$username] !== '' && hash_equals($accounts[$username], $password)) {
        setcookie('ajwills_access', $env['ACCESS_TOKEN'] ?? '', [
            'expires'  => time() + 60 * 60 * 24 * 30,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        header('Location: /');
        exit;
    }

    $error = 'Incorrect username or password.';
}
?>
<!DOCTYPE html>
<html lang="en-GB">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Team Login | AJ Wills</title>
  <meta name="robots" content="noindex, nofollow">
  <link rel="stylesheet" href="/assets/css/style.css?v=1">
  <link rel="icon" type="image/png" href="/assets/images/favicon.png">
  <style>
    body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: var(--color-bg); padding: var(--space-6); }
    .login-card { background: var(--color-bg-panel); border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); padding: var(--space-10); max-width: 380px; width: 100%; text-align: center; }
    .login-card img { max-width: 140px; margin: 0 auto var(--space-6); display: block; }
    .login-card h1 { font-family: var(--font-heading); font-size: 1.4rem; color: var(--olive-700); margin-bottom: var(--space-5); }
    .login-card .form-error-box { background: var(--terracotta-50); color: var(--terracotta-600); border-radius: var(--radius-md); padding: var(--space-3); font-size: 0.85rem; margin-bottom: var(--space-5); }
    .login-card label { display: block; text-align: left; font-size: 0.85rem; font-weight: 600; color: var(--color-text); margin-bottom: var(--space-2); }
    .login-card input { width: 100%; padding: var(--space-3); border: 1px solid var(--color-border); border-radius: var(--radius-md); font-family: var(--font-body); font-size: 1rem; margin-bottom: var(--space-5); }
    .login-card button { width: 100%; }
  </style>
</head>
<body>
  <div class="login-card">
    <img src="/assets/images/aj-wills-logo.png" alt="AJ Wills & Estate Planning">
    <h1>Team Login</h1>
    <?php if ($error !== ''): ?>
      <div class="form-error-box"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post" action="/login.php">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" autocomplete="username" required autofocus>
      <label for="password">Password</label>
      <input type="password" id="password" name="password" autocomplete="current-password" required>
      <button type="submit" class="btn btn-primary">Log In</button>
    </form>
  </div>
</body>
</html>
