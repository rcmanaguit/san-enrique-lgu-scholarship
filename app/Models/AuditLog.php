<?php

namespace App\Models;

use App\Config\Database;

class AuditLog
{
    public static function record(
        ?int $userId,
        ?string $actorRole,
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        array $metadata = []
    ): bool {
        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO audit_logs
                (user_id, actor_role, action, entity_type, entity_id, description, metadata_json, ip_address, user_agent)
            VALUES
                (:user_id, :actor_role, :action, :entity_type, :entity_id, :description, :metadata_json, :ip_address, :user_agent)
        ");

        return $stmt->execute([
            'user_id' => $userId,
            'actor_role' => $actorRole,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
            'metadata_json' => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
            'ip_address' => self::clientIpAddress(),
            'user_agent' => self::userAgent(),
        ]);
    }

    public static function recordCurrentUser(
        string $action,
        string $entityType,
        ?int $entityId,
        string $description,
        array $metadata = []
    ): bool {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        $actorRole = isset($_SESSION['role']) ? (string) $_SESSION['role'] : null;

        return self::record($userId, $actorRole, $action, $entityType, $entityId, $description, $metadata);
    }

    public static function latest(array $filters = [], int $limit = 200): array
    {
        $db = Database::connect();
        $conditions = ['1=1'];
        $params = [];

        if (($filters['actor_role'] ?? '') !== '') {
            $conditions[] = 'al.actor_role = :actor_role';
            $params['actor_role'] = (string) $filters['actor_role'];
        }

        if (($filters['action'] ?? '') !== '') {
            $conditions[] = 'al.action = :action';
            $params['action'] = (string) $filters['action'];
        }

        if (($filters['entity_type'] ?? '') !== '') {
            $conditions[] = 'al.entity_type = :entity_type';
            $params['entity_type'] = (string) $filters['entity_type'];
        }

        $whereSql = implode(' AND ', $conditions);
        $stmt = $db->prepare("
            SELECT
                al.*,
                u.phone_number,
                u.email
            FROM audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE $whereSql
            ORDER BY al.created_at DESC, al.id DESC
            LIMIT :limit
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(':' . $key, $value, \PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function distinctValues(string $column): array
    {
        $allowedColumns = ['actor_role', 'action', 'entity_type'];
        if (!in_array($column, $allowedColumns, true)) {
            return [];
        }

        $db = Database::connect();
        $stmt = $db->query("SELECT DISTINCT {$column} AS value FROM audit_logs WHERE {$column} IS NOT NULL AND {$column} <> '' ORDER BY {$column} ASC");

        return array_values(array_filter(array_map(static fn ($row) => (string) ($row['value'] ?? ''), $stmt->fetchAll())));
    }

    private static function clientIpAddress(): ?string
    {
        $ip = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        return $ip !== '' ? substr($ip, 0, 45) : null;
    }

    private static function userAgent(): ?string
    {
        $userAgent = trim((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''));
        return $userAgent !== '' ? substr($userAgent, 0, 255) : null;
    }
}
