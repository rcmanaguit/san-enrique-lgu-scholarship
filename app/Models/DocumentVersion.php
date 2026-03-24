<?php

namespace App\Models;

use App\Config\Database;

class DocumentVersion
{
    public static function createSnapshot(
        int $documentId,
        string $filePath,
        string $documentStatus,
        ?string $rejectionRemarks,
        ?int $uploadedByUserId,
        string $sourceAction
    ): bool {
        $db = Database::connect();
        $versionNumber = self::nextVersionNumber($documentId);

        $stmt = $db->prepare("
            INSERT INTO document_versions
                (document_id, version_number, file_path, document_status, rejection_remarks, source_action, uploaded_by_user_id)
            VALUES
                (:document_id, :version_number, :file_path, :document_status, :rejection_remarks, :source_action, :uploaded_by_user_id)
        ");

        return $stmt->execute([
            'document_id' => $documentId,
            'version_number' => $versionNumber,
            'file_path' => $filePath,
            'document_status' => $documentStatus,
            'rejection_remarks' => $rejectionRemarks,
            'source_action' => $sourceAction,
            'uploaded_by_user_id' => $uploadedByUserId,
        ]);
    }

    public static function updateLatestReviewState(int $documentId, string $documentStatus, ?string $rejectionRemarks): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            UPDATE document_versions
            SET document_status = :document_status,
                rejection_remarks = :rejection_remarks
            WHERE document_id = :document_id
            ORDER BY version_number DESC
            LIMIT 1
        ");

        return $stmt->execute([
            'document_id' => $documentId,
            'document_status' => $documentStatus,
            'rejection_remarks' => $rejectionRemarks,
        ]);
    }

    public static function historyForApplicationIds(array $applicationIds): array
    {
        if ($applicationIds === []) {
            return [];
        }

        $db = Database::connect();
        $placeholders = implode(',', array_fill(0, count($applicationIds), '?'));
        $stmt = $db->prepare("
            SELECT
                dv.*,
                d.application_id,
                d.document_type,
                a.school_year,
                a.semester,
                u.role AS uploader_role
            FROM document_versions dv
            JOIN documents d ON dv.document_id = d.id
            JOIN applications a ON d.application_id = a.id
            LEFT JOIN users u ON dv.uploaded_by_user_id = u.id
            WHERE d.application_id IN ($placeholders)
            ORDER BY dv.created_at DESC, dv.id DESC
        ");
        $stmt->execute(array_map('intval', $applicationIds));

        return $stmt->fetchAll();
    }

    public static function historyForDocumentIds(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        $db = Database::connect();
        $placeholders = implode(',', array_fill(0, count($documentIds), '?'));
        $stmt = $db->prepare("
            SELECT
                dv.*,
                d.document_type,
                u.role AS uploader_role
            FROM document_versions dv
            JOIN documents d ON dv.document_id = d.id
            LEFT JOIN users u ON dv.uploaded_by_user_id = u.id
            WHERE dv.document_id IN ($placeholders)
            ORDER BY dv.document_id ASC, dv.version_number DESC, dv.id DESC
        ");
        $stmt->execute(array_map('intval', $documentIds));

        return $stmt->fetchAll();
    }

    private static function nextVersionNumber(int $documentId): int
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT COALESCE(MAX(version_number), 0) FROM document_versions WHERE document_id = :document_id");
        $stmt->execute(['document_id' => $documentId]);

        return ((int) $stmt->fetchColumn()) + 1;
    }
}
