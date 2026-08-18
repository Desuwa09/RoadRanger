<?php
/**
 * Local configuration loader for pages/admin/
 * Loads GEMINI_API_KEY from multiple possible locations
 */

function load_gemini_key_safe() {
    // Try to load from environment first (if set via server config)
    $key = getenv('GEMINI_API_KEY');
    if ($key && trim((string)$key) !== '') {
        return $key;
    }

    // Try to load from .env file at project root
    $env_paths = array(
        __DIR__ . '/../../.env',
        __DIR__ . '/../../../.env',
        '/workspaces/RoadRanger/.env',
        $_SERVER['DOCUMENT_ROOT'] . '/.env' ?? null,
    );

    foreach ($env_paths as $env_file) {
        if (!$env_file || !is_file($env_file)) {
            continue;
        }

        $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '' || strpos($trimmed, '#') === 0) {
                continue;
            }

            if (strpos($trimmed, '=') === false) {
                continue;
            }

            list($name, $value) = explode('=', $trimmed, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === 'GEMINI_API_KEY' && $value !== '') {
                if (preg_match('/^(".*"|\'.*\')$/', $value)) {
                    $value = substr($value, 1, -1);
                }
                return $value;
            }
        }
    }

    return null;
}

$GEMINI_API_KEY = load_gemini_key_safe();
