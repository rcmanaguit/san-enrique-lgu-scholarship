<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class Application
{

    // Get the full history for the Student Portal Timeline
    public static function getStudentHistory($userId)
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            SELECT a.*, p.school_name, p.course 
            FROM applications a
            JOIN student_profiles p ON a.student_id = p.id
            WHERE p.user_id = :uid
            ORDER BY a.created_at DESC
        ");
        $stmt->execute(['uid' => $userId]);
        return $stmt->fetchAll();
    }

    // Get pending applications for the Staff Dashboard
    public static function getPendingForStaff()
    {
        $db = Database::connect();
        $stmt = $db->query("
            SELECT a.id, a.status, p.first_name, p.last_name, p.school_name, p.address_barangay 
            FROM applications a 
            JOIN student_profiles p ON a.student_id = p.id 
            WHERE a.status IN ('Submitted', 'Initial_Review', 'SOA_Under_Review', 'Pending_Resubmission')
            ORDER BY a.created_at ASC
        ");
        return $stmt->fetchAll();
    }

    // Safely update the status of an application
    public static function updateStatus($applicationId, $newStatus)
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE applications SET status = :status WHERE id = :id");
        return $stmt->execute(['status' => $newStatus, 'id' => $applicationId]);
    }

    // Get statistics for the Admin Dashboard
    public static function getAdminStats()
    {
        $db = Database::connect();

        $stats = [];
        // Total budget used
        $stats['total_budget'] = $db->query("SELECT SUM(final_grant_amount) FROM applications WHERE status IN ('Approved_Pending_Payroll', 'Approved_Finished')")->fetchColumn();

        // Count by Status
        $stats['approved_count'] = $db->query("SELECT COUNT(*) FROM applications WHERE status IN ('Approved_Pending_Payroll', 'Approved_Finished')")->fetchColumn();
        $stats['forfeited_count'] = $db->query("SELECT COUNT(*) FROM applications WHERE status = 'Forfeited'")->fetchColumn();

        return $stats;
    }
}