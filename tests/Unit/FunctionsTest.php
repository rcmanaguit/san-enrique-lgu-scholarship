<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase
{
    public function testNormalizeBarangayReturnsCanonicalValue(): void
    {
        $this->assertSame('Tibsoc', normalize_barangay('tibsoc'));
        $this->assertSame('Poblacion', normalize_barangay(' POBLACION '));
    }

    public function testNormalizeBarangayReturnsEmptyForInvalidValue(): void
    {
        $this->assertSame('', normalize_barangay('Unknown Barangay'));
    }

    public function testScholarshipCourseValidation(): void
    {
        $this->assertTrue(is_valid_scholarship_course('BS Information Technology'));
        $this->assertTrue(is_valid_scholarship_course('bs information technology'));
        $this->assertFalse(is_valid_scholarship_course('BS Public Administration'));
    }

    public function testNormalizeSchoolNameByAlias(): void
    {
        $this->assertSame(
            'University of St. La Salle',
            normalize_school_name('university of saint la salle')
        );
    }

    public function testExtractQrIdentifiersFromJsonPayload(): void
    {
        $json = json_encode([
            'qr_token' => 'QR-ABC-123',
            'application_no' => 'SE-2026-0001',
        ], JSON_UNESCAPED_SLASHES);

        $result = extract_qr_identifiers((string) $json);

        $this->assertSame('QR-ABC-123', $result['qr_token']);
        $this->assertSame('SE-2026-0001', $result['application_no']);
        $this->assertIsArray($result['payload']);
    }

    public function testExtractQrIdentifiersFromLegacyRawStrings(): void
    {
        $fromQr = extract_qr_identifiers('QR-RAW-TOKEN');
        $this->assertSame('QR-RAW-TOKEN', $fromQr['qr_token']);
        $this->assertNull($fromQr['application_no']);

        $fromApplicationNo = extract_qr_identifiers('SE-2026-0042');
        $this->assertNull($fromApplicationNo['qr_token']);
        $this->assertSame('SE-2026-0042', $fromApplicationNo['application_no']);
    }

    public function testApplicationStatusOptionsContainExpectedWorkflowStatuses(): void
    {
        $statuses = application_status_options();

        $this->assertContains('under_review', $statuses);
        $this->assertContains('needs_resubmission', $statuses);
        $this->assertContains('for_interview', $statuses);
        $this->assertContains('interview_passed', $statuses);
        $this->assertContains('for_soa', $statuses);
        $this->assertContains('soa_received', $statuses);
        $this->assertContains('awaiting_payout', $statuses);
        $this->assertContains('disbursed', $statuses);
        $this->assertContains('rejected', $statuses);
    }

    public function testApprovedPhaseStatusesAreSubsetOfApplicationStatuses(): void
    {
        $all = application_status_options();
        $approved = approved_phase_statuses();

        foreach ($approved as $status) {
            $this->assertContains($status, $all);
        }
    }

    public function testStatusBadgeClassMappings(): void
    {
        $this->assertSame('text-bg-info', status_badge_class('under_review'));
        $this->assertSame('text-bg-warning', status_badge_class('needs_resubmission'));
        $this->assertSame('text-bg-success', status_badge_class('disbursed'));
        $this->assertSame('text-bg-danger', status_badge_class('rejected'));
        $this->assertSame('text-bg-secondary', status_badge_class('awaiting_payout'));
        $this->assertSame('text-bg-light', status_badge_class('unknown_status'));
    }

    public function testFormatApplicationPeriodWithDates(): void
    {
        $period = [
            'academic_year' => '2026-2027',
            'semester' => 'First Semester',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-14',
        ];

        $this->assertSame(
            'First Semester 2026-2027 (Apr 01, 2026 - Apr 14, 2026)',
            format_application_period($period)
        );
    }

    public function testFormatApplicationPeriodFallbackNameOnly(): void
    {
        $period = [
            'period_name' => 'Special Intake Window',
            'start_date' => '',
            'end_date' => '',
        ];

        $this->assertSame('Special Intake Window', format_application_period($period));
    }

    public function testCalculateAgeFromBirthDate(): void
    {
        $today = new DateTimeImmutable('today');
        $birthDate = $today->modify('-20 years')->format('Y-m-d');

        $this->assertSame(20, calculate_age_from_birth_date($birthDate));
    }

    public function testCalculateAgeReturnsNullForInvalidOrFutureDate(): void
    {
        $future = (new DateTimeImmutable('today'))->modify('+1 day')->format('Y-m-d');

        $this->assertNull(calculate_age_from_birth_date('invalid-date'));
        $this->assertNull(calculate_age_from_birth_date($future));
    }
}
