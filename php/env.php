<?php
/**
 * AJ Wills & Estate Planning — Shared .env loader
 * Used by the lead-capture endpoints to read private settings (like the
 * lead notification recipient) without hardcoding them in source control.
 */

declare(strict_types=1);

function load_env(string $path): array {
    $env = [];
    if (!file_exists($path)) {
        return $env;
    }
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
