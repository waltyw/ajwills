<?php
declare(strict_types=1);

setcookie('ajwills_access', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => true,
    'httponly' => true,
    'samesite' => 'Lax',
]);

header('Location: /login.php');
exit;
