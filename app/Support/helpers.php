<?php

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
