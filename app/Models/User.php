<?php

namespace App\Models;

use App\Config\Database;
use PDO;

class User
{

    // Find a user by their phone number (Used in Login)
    public static function findByPhone($phone)
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE phone_number = :phone LIMIT 1");
        $stmt->execute(['phone' => $phone]);
        return $stmt->fetch(); // Returns the user array or false
    }

    public static function findByEmail(string $email)
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        return $stmt->fetch();
    }

    // Find a user by their ID
    public static function findById($id)
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public static function allWithStats(): array
    {
        $db = Database::connect();
        $stmt = $db->query("
            SELECT
                u.*,
                COALESCE(NULLIF(u.first_name, ''), sp.first_name) AS display_first_name,
                COALESCE(NULLIF(u.last_name, ''), sp.last_name) AS display_last_name,
                sp.school_name,
                COUNT(a.id) AS total_applications
            FROM users u
            LEFT JOIN student_profiles sp ON sp.user_id = u.id
            LEFT JOIN applications a ON sp.id = a.student_id
            WHERE u.role IN ('Staff', 'Admin')
            GROUP BY
                u.id, u.role, u.first_name, u.last_name, u.phone_number, u.email, u.password_hash, u.is_verified, u.is_active,
                u.otp_code, u.otp_expires_at, u.created_at, u.updated_at,
                sp.first_name, sp.last_name, sp.school_name
            ORDER BY u.created_at DESC, u.id DESC
        ");
        return $stmt->fetchAll();
    }

    public static function createAccount(string $role, string $firstName, string $lastName, string $phoneNumber, ?string $email, string $passwordHash, int $isVerified = 1): int
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO users (role, first_name, last_name, phone_number, email, password_hash, is_verified, is_active)
            VALUES (:role, :first_name, :last_name, :phone_number, :email, :password_hash, :is_verified, 1)
        ");
        $stmt->execute([
            'role' => $role,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone_number' => $phoneNumber,
            'email' => $email,
            'password_hash' => $passwordHash,
            'is_verified' => $isVerified,
        ]);

        return (int) $db->lastInsertId();
    }

    public static function setActiveStatus(int $userId, bool $isActive): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET is_active = :is_active WHERE id = :id");
        return $stmt->execute([
            'id' => $userId,
            'is_active' => $isActive ? 1 : 0,
        ]);
    }

    public static function storeOtp(int $userId, string $otpCode, string $expiresAt): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET otp_code = :otp, otp_expires_at = :expires WHERE id = :id");

        return $stmt->execute([
            'id' => $userId,
            'otp' => $otpCode,
            'expires' => $expiresAt,
        ]);
    }

    public static function hasValidOtp(int $userId, string $otpCode): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("SELECT otp_expires_at FROM users WHERE id = :id AND otp_code = :otp LIMIT 1");
        $stmt->execute(['id' => $userId, 'otp' => $otpCode]);
        $row = $stmt->fetch();
        if (!$row || empty($row['otp_expires_at'])) {
            return false;
        }

        try {
            $expiresAt = new \DateTimeImmutable((string) $row['otp_expires_at']);
            $now = new \DateTimeImmutable('now');
            return $expiresAt > $now;
        } catch (\Throwable $exception) {
            return false;
        }
    }

    public static function clearOtp(int $userId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET otp_code = NULL, otp_expires_at = NULL WHERE id = :id");

        return $stmt->execute(['id' => $userId]);
    }

    public static function updatePassword(int $userId, string $passwordHash): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET password_hash = :password_hash WHERE id = :id");

        return $stmt->execute([
            'id' => $userId,
            'password_hash' => $passwordHash,
        ]);
    }

    public static function updateRecoveryContact(int $userId, ?string $phoneNumber, ?string $email): bool
    {
        $db = Database::connect();
        $fields = [];
        $params = ['id' => $userId];

        if ($phoneNumber !== null) {
            $fields[] = 'phone_number = :phone_number';
            $params['phone_number'] = $phoneNumber;
        }

        if ($email !== null) {
            $fields[] = 'email = :email';
            $params['email'] = $email;
        }

        if (empty($fields)) {
            return true;
        }

        $stmt = $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id');
        return $stmt->execute($params);
    }

    public static function updateName(int $userId, string $firstName, string $lastName): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("UPDATE users SET first_name = :first_name, last_name = :last_name WHERE id = :id");

        return $stmt->execute([
            'id' => $userId,
            'first_name' => $firstName,
            'last_name' => $lastName,
        ]);
    }

    // Mark user as verified and clear the OTP
    public static function verifyOTP($userId, $otpCode)
    {
        if (self::hasValidOtp((int) $userId, (string) $otpCode)) {
            // It's valid! Update the user.
            $db = Database::connect();
            $update = $db->prepare("UPDATE users SET is_verified = 1, otp_code = NULL, otp_expires_at = NULL WHERE id = :id");
            return $update->execute(['id' => $userId]);
        }

        return false; // OTP was wrong or expired
    }
}
