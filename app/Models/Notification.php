<?php

namespace App\Models;

use App\Config\Database;

class Notification
{
    public static function create(int $userId, string $title, string $message, ?string $link = null): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "INSERT INTO notifications (user_id, title, message, link_path) VALUES (:user_id, :title, :message, :link_path)"
        );

        return $stmt->execute([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'link_path' => $link,
        ]);
    }

    public static function createForRoles(array $roles, string $title, string $message, ?string $link = null): void
    {
        $roles = array_values(array_filter(array_unique(array_map('strval', $roles))));
        if ($roles === []) {
            return;
        }

        $db = Database::connect();
        $placeholders = implode(',', array_fill(0, count($roles), '?'));
        $stmt = $db->prepare("SELECT id FROM users WHERE role IN ($placeholders)");
        $stmt->execute($roles);
        $userIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        foreach ($userIds as $userId) {
            self::create((int) $userId, $title, $message, $link);
        }
    }

    public static function latestForUser(int $userId, int $limit = 8): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT id, title, message, link_path, is_read, created_at
             FROM notifications
             WHERE user_id = :user_id
             ORDER BY created_at DESC, id DESC
             LIMIT :limit"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function unreadCountForUser(int $userId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public static function markAllAsReadForUser(int $userId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :user_id AND is_read = 0");

        return $stmt->execute(['user_id' => $userId]);
    }

    public static function markAsReadForUser(int $notificationId, int $userId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "UPDATE notifications
             SET is_read = 1
             WHERE id = :notification_id AND user_id = :user_id AND is_read = 0"
        );

        return $stmt->execute([
            'notification_id' => $notificationId,
            'user_id' => $userId,
        ]);
    }
}
