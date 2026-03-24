<?php

namespace App\Models;

use App\Config\Database;

class CaseNote
{
    public static function latestForApplication(int $applicationId, int $limit = 20): array
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT cn.*, u.role AS author_role
            FROM case_notes cn
            LEFT JOIN users u ON cn.user_id = u.id
            WHERE cn.application_id = :application_id
            ORDER BY cn.created_at DESC, cn.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute(['application_id' => $applicationId]);
        return $stmt->fetchAll();
    }

    public static function latestForApplications(array $applicationIds, int $limit = 50): array
    {
        if ($applicationIds === []) {
            return [];
        }

        $db = Database::connect();
        $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
        $stmt = $db->prepare("
            SELECT cn.*, u.role AS author_role
            FROM case_notes cn
            LEFT JOIN users u ON cn.user_id = u.id
            WHERE cn.application_id IN ($placeholders)
            ORDER BY cn.created_at DESC, cn.id DESC
            LIMIT {$limit}
        ");
        $stmt->execute(array_map('intval', $applicationIds));
        return $stmt->fetchAll();
    }

    public static function create(int $applicationId, ?int $userId, string $noteText): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO case_notes (application_id, user_id, note_text)
            VALUES (:application_id, :user_id, :note_text)
        ");
        return $stmt->execute([
            'application_id' => $applicationId,
            'user_id' => $userId,
            'note_text' => $noteText,
        ]);
    }
}
