<?php

namespace App\Controllers;

use App\Models\AuditLog;
use App\Models\Notification;

class NotificationController
{
    private int $userId;

    public function __construct()
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: ' . \base_url('login'));
            exit;
        }

        $this->userId = (int) $_SESSION['user_id'];
    }

    public function markAllAsRead()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        Notification::markAllAsReadForUser($this->userId);
        AuditLog::recordCurrentUser('notification.mark_all_read', 'notification', null, 'User marked all notifications as read.');

        if ($this->expectsJson()) {
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode(['success' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            exit;
        }

        $redirectPath = trim((string) ($_POST['redirect_to'] ?? ''));
        if ($redirectPath === '') {
            $redirectPath = trim((string) parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH), '/');
        }

        $basePath = trim((string) \app_base_path(), '/');
        if ($basePath !== '' && str_starts_with($redirectPath, $basePath)) {
            $redirectPath = trim(substr($redirectPath, strlen($basePath)), '/');
        }

        header('Location: ' . \base_url($redirectPath));
        exit;
    }

    public function markAsRead()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return;
        }

        $notificationId = (int) ($_POST['notification_id'] ?? 0);
        if ($notificationId > 0) {
            Notification::markAsReadForUser($notificationId, $this->userId);
            AuditLog::recordCurrentUser(
                'notification.mark_read',
                'notification',
                $notificationId,
                'User marked a notification as read.'
            );
        }

        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['success' => true], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    public function feed()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $notifications = array_map(static function (array $notification): array {
            $linkPath = trim((string) ($notification['link_path'] ?? ''));
            $notification['href'] = $linkPath !== '' ? \base_url($linkPath) : '#';
            return $notification;
        }, Notification::latestForUser($this->userId, 8));

        echo json_encode([
            'unread_count' => Notification::unreadCountForUser($this->userId),
            'notifications' => $notifications,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function expectsJson(): bool
    {
        $acceptHeader = strtolower((string) ($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        return str_contains($acceptHeader, 'application/json') || $requestedWith === 'xmlhttprequest';
    }
}
