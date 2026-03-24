<?php

namespace App\Models;

use App\Config\Database;
use DateTimeImmutable;
use PDO;

class ApplicationPeriod
{
    public static function getCurrent(): array
    {
        $db = Database::connect();
        $stmt = $db->query("SELECT * FROM application_settings WHERE id = 1 LIMIT 1");
        $settings = $stmt->fetch();

        if (!$settings) {
            return [
                'school_year' => '',
                'semester' => '',
                'application_start_date' => '',
                'application_end_date' => '',
                'soa_deadline_mode' => 'Manual',
                'soa_deadline_days_after_passed' => null,
                'soa_deadline' => '',
                'auto_archive_completed_records' => 1,
                'is_open' => 0,
                'updated_at' => null,
            ];
        }

        return $settings;
    }

    public static function save(array $data): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare("
            INSERT INTO application_settings (id, school_year, semester, application_start_date, application_end_date, soa_deadline_mode, soa_deadline_days_after_passed, soa_deadline, auto_archive_completed_records, is_open)
            VALUES (1, :school_year, :semester, :start_date, :end_date, :soa_deadline_mode, :soa_deadline_days_after_passed, :soa_deadline, :auto_archive_completed_records, :is_open)
            ON DUPLICATE KEY UPDATE
                school_year = VALUES(school_year),
                semester = VALUES(semester),
                application_start_date = VALUES(application_start_date),
                application_end_date = VALUES(application_end_date),
                soa_deadline_mode = VALUES(soa_deadline_mode),
                soa_deadline_days_after_passed = VALUES(soa_deadline_days_after_passed),
                soa_deadline = VALUES(soa_deadline),
                auto_archive_completed_records = VALUES(auto_archive_completed_records),
                is_open = VALUES(is_open)
        ");

        return $stmt->execute([
            'school_year' => $data['school_year'],
            'semester' => $data['semester'],
            'start_date' => $data['application_start_date'],
            'end_date' => $data['application_end_date'],
            'soa_deadline_mode' => $data['soa_deadline_mode'] ?? 'Manual',
            'soa_deadline_days_after_passed' => $data['soa_deadline_days_after_passed'] ?? null,
            'soa_deadline' => $data['soa_deadline'] ?: null,
            'auto_archive_completed_records' => $data['auto_archive_completed_records'] ?? 1,
            'is_open' => $data['is_open'],
        ]);
    }

    public static function isAcceptingApplications(array $settings): bool
    {
        if ((int) ($settings['is_open'] ?? 0) !== 1) {
            return false;
        }

        $start = trim((string) ($settings['application_start_date'] ?? ''));
        $end = trim((string) ($settings['application_end_date'] ?? ''));
        if ($start === '' || $end === '') {
            return false;
        }

        try {
            $today = new DateTimeImmutable('today');
            $startDate = new DateTimeImmutable($start);
            $endDate = new DateTimeImmutable($end);

            return $today >= $startDate && $today <= $endDate;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function activePeriodLabel(array $settings): string
    {
        $schoolYear = trim((string) ($settings['school_year'] ?? ''));
        $semester = trim((string) ($settings['semester'] ?? ''));

        if ($schoolYear === '' || $semester === '') {
            return 'No active application period';
        }

        return 'School Year ' . $schoolYear . ' | ' . $semester;
    }

    public static function hasActiveSoaDeadline(array $settings): bool
    {
        $deadline = trim((string) ($settings['soa_deadline'] ?? ''));
        if ($deadline === '') {
            return false;
        }

        try {
            $today = new DateTimeImmutable('today');
            $deadlineDate = new DateTimeImmutable($deadline);
            return $today <= $deadlineDate;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function soaDeadlinePolicyLabel(array $settings): string
    {
        $mode = (string) ($settings['soa_deadline_mode'] ?? 'Manual');
        if ($mode === 'AfterPassed') {
            $days = (int) ($settings['soa_deadline_days_after_passed'] ?? 0);
            return $days > 0 ? ($days . ' days after passed interview') : 'Days after passed interview';
        }

        return 'Manual fixed date';
    }

    public static function resolveApplicationSoaDeadline(array $settings, array $application): string
    {
        $applicationDeadline = trim((string) ($application['soa_deadline'] ?? ''));
        if ($applicationDeadline !== '') {
            return substr($applicationDeadline, 0, 10);
        }

        $mode = (string) ($settings['soa_deadline_mode'] ?? 'Manual');
        if ($mode === 'Manual') {
            return trim((string) ($settings['soa_deadline'] ?? ''));
        }

        $interviewResultAt = trim((string) ($application['interview_result_at'] ?? ''));
        if ($interviewResultAt === '') {
            return '';
        }

        $daysToAdd = $mode === 'AfterPassed' ? (int) ($settings['soa_deadline_days_after_passed'] ?? 0) : 0;

        if ($daysToAdd <= 0) {
            return '';
        }

        try {
            return (new DateTimeImmutable($interviewResultAt))
                ->modify('+' . $daysToAdd . ' days')
                ->format('Y-m-d');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function isApplicationSoaDeadlineOpen(array $settings, array $application): bool
    {
        $deadline = self::resolveApplicationSoaDeadline($settings, $application);
        if ($deadline === '') {
            return true;
        }

        try {
            $today = new DateTimeImmutable('today');
            $deadlineDate = new DateTimeImmutable($deadline);
            return $today <= $deadlineDate;
        } catch (\Throwable) {
            return false;
        }
    }

    public static function syncOverdueSoaStatuses(?PDO $db = null, ?array $settings = null): int
    {
        $db ??= Database::connect();
        $settings ??= self::getCurrent();

        $stmt = $db->query("
            SELECT id, status, soa_deadline, interview_result_at
            FROM applications
            WHERE status IN ('Eligible_Awaiting_SOA', 'SOA_Resubmission_Required', 'SOA_Overdue')
        ");

        $updatedCount = 0;
        foreach ($stmt->fetchAll() as $application) {
            $applicationId = (int) ($application['id'] ?? 0);
            if ($applicationId <= 0) {
                continue;
            }

            $status = (string) ($application['status'] ?? '');
            $deadline = self::resolveApplicationSoaDeadline($settings, $application);
            $isOpen = self::isApplicationSoaDeadlineOpen($settings, $application);

            if ($deadline !== '' && !$isOpen && in_array($status, ['Eligible_Awaiting_SOA', 'SOA_Resubmission_Required'], true)) {
                $updateStmt = $db->prepare("UPDATE applications SET status = 'SOA_Overdue' WHERE id = :id");
                $updateStmt->execute(['id' => $applicationId]);
                $updatedCount += $updateStmt->rowCount();
            }
        }

        return $updatedCount;
    }
}
