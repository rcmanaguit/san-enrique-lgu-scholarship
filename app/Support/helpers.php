<?php

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (!isset($_SESSION['_csrf_token']) || !is_string($_SESSION['_csrf_token']) || $_SESSION['_csrf_token'] === '') {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }
}

if (!function_exists('csrf_input')) {
    function csrf_input(): string
    {
        return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

if (!function_exists('csrf_request_token')) {
    function csrf_request_token(): string
    {
        $headerToken = trim((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''));
        if ($headerToken !== '') {
            return $headerToken;
        }

        return trim((string) ($_POST['_csrf'] ?? ''));
    }
}

if (!function_exists('csrf_is_valid')) {
    function csrf_is_valid(): bool
    {
        $sessionToken = (string) ($_SESSION['_csrf_token'] ?? '');
        $requestToken = csrf_request_token();

        return $sessionToken !== '' && $requestToken !== '' && hash_equals($sessionToken, $requestToken);
    }
}

if (!function_exists('client_ip')) {
    function client_ip(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value === '') {
                continue;
            }

            if (str_contains($value, ',')) {
                $value = trim((string) explode(',', $value)[0]);
            }

            if (filter_var($value, FILTER_VALIDATE_IP)) {
                return $value;
            }
        }

        return 'unknown';
    }
}

if (!function_exists('app_base_path')) {
    function app_base_path(): string
    {
        $basePath = $_SERVER['APP_BASE_PATH'] ?? ($_ENV['APP_BASE_PATH'] ?? '');
        $normalized = '/' . trim($basePath, '/');

        return $normalized === '/' ? '' : $normalized;
    }
}

if (!function_exists('base_url')) {
    function base_url(string $path = ''): string
    {
        $basePath = app_base_path();
        $trimmedPath = trim($path, '/');

        if ($trimmedPath === '') {
            return $basePath !== '' ? $basePath . '/' : '/';
        }

        return ($basePath !== '' ? $basePath : '') . '/' . $trimmedPath;
    }
}

if (!function_exists('asset_url')) {
    function asset_url(string $path): string
    {
        return base_url('assets/' . ltrim($path, '/'));
    }
}

if (!function_exists('storage_path')) {
    function storage_path(string $path = ''): string
    {
        $configuredRoot = trim((string) ($_ENV['APP_STORAGE_PATH'] ?? ''), " \t\n\r\0\x0B\\/");

        if ($configuredRoot !== '') {
            $root = rtrim($configuredRoot, DIRECTORY_SEPARATOR . '/\\');
        } else {
            $root = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage';
        }

        $trimmedPath = trim($path, " \t\n\r\0\x0B\\/");
        if ($trimmedPath === '') {
            return $root;
        }

        return $root . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $trimmedPath);
    }
}

if (!function_exists('set_flash')) {
    function set_flash(string $type, string $message): void
    {
        $_SESSION['_flash_messages'][] = [
            'type' => $type,
            'message' => $message,
        ];
    }
}

if (!function_exists('get_flash_messages')) {
    function get_flash_messages(): array
    {
        $messages = $_SESSION['_flash_messages'] ?? [];
        unset($_SESSION['_flash_messages']);

        return is_array($messages) ? $messages : [];
    }
}

if (!function_exists('redirect_with_flash')) {
    function redirect_with_flash(string $path, string $type, string $message): never
    {
        set_flash($type, $message);
        header('Location: ' . base_url($path));
        exit;
    }
}

if (!function_exists('canonical_document_type')) {
    function canonical_document_type(string $type): string
    {
        $normalized = trim($type);

        return $normalized === 'Residency' ? 'Barangay Residency' : $normalized;
    }
}

if (!function_exists('document_type_matches')) {
    function document_type_matches(?string $value, string $expected): bool
    {
        return canonical_document_type((string) $value) === canonical_document_type($expected);
    }
}

if (!function_exists('document_type_label')) {
    function document_type_label(?string $type): string
    {
        $normalized = canonical_document_type((string) $type);

        return $normalized !== '' ? $normalized : 'Document';
    }
}
