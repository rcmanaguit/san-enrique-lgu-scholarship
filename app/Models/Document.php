<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Document
{

    // Fetch all documents attached to a specific application ID (Used in Staff Split-Screen)
    public static function getByApplicationId($applicationId)
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM documents WHERE application_id = :aid");
        $stmt->execute(['aid' => $applicationId]);
        return $stmt->fetchAll();
    }

    // Update a single document's status (Verified/Rejected) and add remarks
    public static function updateStatus($documentId, $status, $remarks = null)
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE documents SET status = :status, rejection_remarks = :remarks WHERE id = :id");
        return $stmt->execute([
            'status' => $status,
            'remarks' => $remarks,
            'id' => $documentId
        ]);
    }

    // Check if an application still has any 'Pending' or 'Rejected' documents
    // (Helps the system know when to automatically move the student to 'For Interview')
    public static function hasUnverifiedDocuments($applicationId)
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT COUNT(*) FROM documents WHERE application_id = :aid AND status != 'Verified'");
        $stmt->execute(['aid' => $applicationId]);

        // Returns true if there are still unverified docs, false if everything is perfect
        return $stmt->fetchColumn() > 0;
    }
}