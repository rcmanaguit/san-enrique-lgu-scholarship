<?php

namespace App\Models;

use App\Config\Database;

class Announcement
{
    public static function latestForAudience(string $audience, int $limit = 5): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT a.*, CONCAT(COALESCE(p.first_name, ''), ' ', COALESCE(p.last_name, '')) AS author_name
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            LEFT JOIN student_profiles p ON p.user_id = u.id
            WHERE a.is_published = 1
              AND a.target_audience IN (:audience, 'Both', 'All')
            ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['audience' => $audience]);
        return $stmt->fetchAll();
    }

    public static function all(): array
    {
        $db = Database::connect();
        $stmt = $db->query("
            SELECT a.*, u.role AS creator_role
            FROM announcements a
            LEFT JOIN users u ON a.created_by = u.id
            ORDER BY COALESCE(a.published_at, a.created_at) DESC, a.id DESC
        ");
        return $stmt->fetchAll();
    }

    public static function create(array $data): int
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO announcements (title, body, target_audience, is_published, published_at, created_by)
            VALUES (:title, :body, :target_audience, :is_published, :published_at, :created_by)
        ");
        $stmt->execute($data);
        return (int) $db->lastInsertId();
    }

    public static function update(int $id, array $data): bool
    {
        $db = Database::connect();
        $data['id'] = $id;
        $stmt = $db->prepare("
            UPDATE announcements
            SET title = :title,
                body = :body,
                target_audience = :target_audience,
                is_published = :is_published,
                published_at = :published_at
            WHERE id = :id
        ");
        return $stmt->execute($data);
    }

    public static function togglePublished(int $id, bool $isPublished): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            UPDATE announcements
            SET is_published = :is_published,
                published_at = CASE WHEN :is_published = 1 THEN NOW() ELSE published_at END
            WHERE id = :id
        ");
        return $stmt->execute([
            'id' => $id,
            'is_published' => $isPublished ? 1 : 0,
        ]);
    }
}
