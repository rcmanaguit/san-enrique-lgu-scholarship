<?php
$naValue = static function ($value): string {
    $text = trim((string) $value);
    return $text === '' ? 'N/A' : $text;
};

$formatDate = static function ($value, string $fallback = 'N/A'): string {
    $normalized = trim((string) $value);
    if ($normalized === '') {
        return $fallback;
    }

    try {
        return (new \DateTimeImmutable($normalized))->format('M d, Y');
    } catch (\Throwable $exception) {
        return $normalized;
    }
};

$formatMoney = static function ($value, string $fallback = 'N/A'): string {
    if ($value === null || $value === '') {
        return $fallback;
    }

    if (!is_numeric($value)) {
        $normalized = trim((string) $value);
        return $normalized === '' ? $fallback : $normalized;
    }

    return 'Php ' . number_format((float) $value, 2);
};

$containsToken = static function (string $haystack, string $needle): bool {
    if ($haystack === '' || $needle === '') {
        return false;
    }

    return stripos($haystack, $needle) !== false;
};

$resolveCourseValue = static function (array $application, array $education): string {
    $directCourse = trim((string) ($application['course'] ?? ''));
    if ($directCourse !== '') {
        return $directCourse;
    }

    foreach ($education as $row) {
        if (!is_array($row)) {
            continue;
        }

        $level = strtolower(trim((string) ($row['level'] ?? '')));
        $course = trim((string) ($row['course'] ?? ''));
        if ($level === 'college' && $course !== '') {
            return $course;
        }
    }

    foreach ($education as $row) {
        if (!is_array($row)) {
            continue;
        }

        $course = trim((string) ($row['course'] ?? ''));
        if ($course !== '') {
            return $course;
        }
    }

    return '';
};

$displayCourse = $resolveCourseValue($application, is_array($education ?? null) ? $education : []);
$applicantType = strtolower(trim((string) ($application['application_type'] ?? $application['applicant_type'] ?? '')));
$addressLine = trim((string) ($application['address_line'] ?? $application['address'] ?? ''));
$barangayLine = trim((string) ($application['address_barangay'] ?? $application['barangay'] ?? ''));
$townLine = trim((string) ($application['town'] ?? 'San Enrique'));
$provinceLine = trim((string) ($application['province'] ?? 'Negros Occidental'));

$addressParts = [];
if ($addressLine !== '') {
    $addressParts[] = $addressLine;
}
if ($barangayLine !== '' && !$containsToken($addressLine, $barangayLine)) {
    $addressParts[] = $barangayLine;
}
if ($townLine !== '' && !$containsToken($addressLine, $townLine)) {
    $addressParts[] = $townLine;
}
if ($provinceLine !== '' && !$containsToken($addressLine, $provinceLine)) {
    $addressParts[] = $provinceLine;
}
$printableAddress = implode(', ', $addressParts);

$siblingsRows = array_values(array_filter(
    is_array($siblings ?? null) ? $siblings : [],
    static function (array $row): bool {
        foreach (['name', 'age', 'education', 'occupation', 'income'] as $key) {
            if (trim((string) ($row[$key] ?? '')) !== '') {
                return true;
            }
        }
        return false;
    }
));

$educationRows = is_array($education ?? null) ? $education : [];
$grantsRows = is_array($grants ?? null) ? $grants : [];

$documentLabel = static function (array $document): string {
    $label = trim((string) ($document['requirement_name'] ?? $document['document_type'] ?? ''));
    if ($label === '') {
        return 'Requirement';
    }

    return $label;
};
?>
<style>
@page {
    size: 8.5in 13in;
    margin: 0;
}

body {
    margin: 0;
    background: #ffffff;
    font-family: "Manrope", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
}

.application-paper {
    width: 8.5in;
    min-height: 13in;
    padding: 0.2in 0.22in;
    color: #1d2d35;
    font-size: 11px;
    font-family: "Manrope", "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
    box-sizing: border-box;
}

.paper-table {
    width: 100%;
    border-collapse: collapse;
}

.paper-table th,
.paper-table td {
    border: 1px solid #6d7d87;
    padding: 3px;
    vertical-align: top;
    line-height: 1.2;
    height: 20px;
}

.paper-title {
    color: #1a98d5;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    text-shadow: 1px 1px 0 #ffb68a;
    font-size: 29px;
    line-height: 1;
    white-space: nowrap;
}

.paper-subtitle {
    font-weight: 700;
    font-size: 12px;
    margin-top: 1px;
    white-space: nowrap;
}

.section-title {
    font-weight: 800;
    margin: 6px 0 2px;
    font-size: 11px;
}

.photo-box {
    width: 2in;
    height: 2in;
    border: 1px solid #6d7d87;
    text-align: center;
    overflow: hidden;
}

.photo-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.small-note {
    font-size: 9px;
}

.govt-header-line-1 {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.15;
}

.govt-header-line-2 {
    font-size: 12px;
    line-height: 1.1;
}

.govt-header-line-3 {
    font-size: 22px;
    font-weight: 800;
    line-height: 1.05;
}

.govt-header-line-4 {
    font-size: 15px;
    font-weight: 700;
    line-height: 1.1;
}

.program-heading-wrap {
    padding-top: 4px;
    text-align: left;
}
</style>

<div class="application-paper">
    <table class="paper-table" style="border:none;">
        <tr>
            <td style="border:none;width:1.2in;padding:0 6px 0 0;vertical-align:top;" rowspan="2">
                <?php if ($logoSrc !== ''): ?>
                    <img
                        src="<?php echo htmlspecialchars($logoSrc); ?>"
                        alt="LGU Logo"
                        style="width:1.05in;height:1.05in;object-fit:contain;display:block;margin:0 auto;"
                    >
                <?php endif; ?>
            </td>
            <td style="border:none;text-align:center;padding:0;" colspan="2">
                <div class="govt-header-line-1">REPUBLIC OF THE PHILIPPINES</div>
                <div class="govt-header-line-2">Province of Negros Occidental</div>
                <div class="govt-header-line-3">MUNICIPALITY OF SAN ENRIQUE</div>
                <div class="govt-header-line-4">OFFICE OF THE MAYOR</div>
            </td>
            <td style="width:1.85in;border:none;vertical-align:top;" rowspan="2">
                <div class="photo-box">
                    <?php if ($photoSrc !== ''): ?>
                        <img src="<?php echo htmlspecialchars($photoSrc); ?>" alt="2x2 Photo">
                    <?php else: ?>
                        <div style="padding-top:38px;">2x2<br>Photo</div>
                    <?php endif; ?>
                </div>
            </td>
        </tr>
        <tr>
            <td colspan="2" class="program-heading-wrap" style="border:none;">
                <div class="paper-title">LGU SCHOLARSHIP PROGRAM</div>
                <div class="paper-subtitle">MUNICIPALITY OF SAN ENRIQUE, NEGROS OCCIDENTAL</div>
            </td>
        </tr>
    </table>

    <div class="section-title">PERSONAL INFORMATION</div>
    <table class="paper-table">
        <tr>
            <td style="width:14%"><strong>LAST NAME</strong></td>
            <td style="width:30%"><?php echo htmlspecialchars($naValue($application['last_name'] ?? '')); ?></td>
            <td style="width:9%"><strong>AGE</strong></td>
            <td style="width:20%"><?php echo htmlspecialchars($naValue($calculatedAge)); ?></td>
            <td style="width:13%;text-align:center;"><strong>NEW</strong><br><span class="small-note">Please check (/)</span><br><?php echo str_contains($applicantType, 'new') ? '✓' : ''; ?></td>
            <td style="width:14%;text-align:center;"><strong>RE-NEW</strong><br><span class="small-note">Please check (/)</span><br><?php echo str_contains($applicantType, 'renew') ? '✓' : ''; ?></td>
        </tr>
        <tr>
            <td><strong>GIVEN NAME</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['first_name'] ?? '')); ?></td>
            <td><strong>CIVIL STATUS</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['civil_status'] ?? '')); ?></td>
            <td><strong>EMAIL</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['email'] ?? '')); ?></td>
        </tr>
        <tr>
            <td><strong>MIDDLE NAME</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['middle_name'] ?? '')); ?></td>
            <td><strong>SUFFIX</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['suffix'] ?? '')); ?></td>
            <td><strong>SEX</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['sex'] ?? '')); ?></td>
        </tr>
        <tr>
            <td><strong>DATE OF BIRTH</strong></td>
            <td><?php echo htmlspecialchars($naValue($formatDate($application['date_of_birth'] ?? $application['birth_date'] ?? '', 'N/A'))); ?></td>
            <td><strong>CONTACT #</strong></td>
            <td colspan="3"><?php echo htmlspecialchars($naValue($application['phone_number'] ?? $application['contact_number'] ?? '')); ?></td>
        </tr>
        <tr>
            <td><strong>PLACE OF BIRTH</strong></td>
            <td colspan="5"><?php echo htmlspecialchars($naValue($application['place_of_birth'] ?? $application['birth_place'] ?? '')); ?></td>
        </tr>
        <tr>
            <td><strong>ADDRESS</strong></td>
            <td colspan="5"><?php echo htmlspecialchars($naValue($printableAddress)); ?></td>
        </tr>
        <tr>
            <td><strong>SCHOOL</strong></td>
            <td colspan="3"><?php echo htmlspecialchars($naValue($application['school_name'] ?? '')); ?></td>
            <td><strong>TYPE</strong></td>
            <td><?php echo htmlspecialchars($naValue(strtoupper((string) ($application['school_type'] ?? '')))); ?></td>
        </tr>
        <tr>
            <td><strong>COURSE</strong></td>
            <td colspan="3"><?php echo htmlspecialchars($naValue($displayCourse)); ?></td>
            <td><strong>SEM / SY</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['semester'] ?? '')); ?> / <?php echo htmlspecialchars($naValue($application['school_year'] ?? '')); ?></td>
        </tr>
    </table>

    <div class="section-title">FAMILY BACKGROUND</div>
    <table class="paper-table">
        <tr>
            <td style="width:17%"><strong>MOTHER'S NAME</strong></td>
            <td style="width:33%"><?php echo htmlspecialchars($naValue($application['mother_name'] ?? '')); ?></td>
            <td style="width:17%"><strong>FATHER'S NAME</strong></td>
            <td style="width:33%"><?php echo htmlspecialchars($naValue($application['father_name'] ?? '')); ?></td>
        </tr>
        <tr>
            <td><strong>AGE</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['mother_age'] ?? '')); ?></td>
            <td><strong>AGE</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['father_age'] ?? '')); ?></td>
        </tr>
        <tr>
            <td><strong>OCCUPATION</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['mother_occupation'] ?? '')); ?></td>
            <td><strong>OCCUPATION</strong></td>
            <td><?php echo htmlspecialchars($naValue($application['father_occupation'] ?? '')); ?></td>
        </tr>
        <tr>
            <td><strong>MONTHLY INCOME</strong></td>
            <td><?php echo htmlspecialchars($naValue($formatMoney($application['mother_monthly_income'] ?? null))); ?></td>
            <td><strong>MONTHLY INCOME</strong></td>
            <td><?php echo htmlspecialchars($naValue($formatMoney($application['father_monthly_income'] ?? null))); ?></td>
        </tr>
    </table>

    <div class="section-title">MEMBERS OF THE FAMILY (SIBLINGS)</div>
    <table class="paper-table">
        <tr>
            <th style="width:32%">Name</th>
            <th style="width:8%">Age</th>
            <th style="width:17%">Highest Educational Attainment</th>
            <th style="width:21%">Occupation</th>
            <th style="width:22%">Monthly Income</th>
        </tr>
        <?php for ($i = 0; $i < 5; $i++): ?>
            <?php $row = $siblingsRows[$i] ?? ['name' => '', 'age' => '', 'education' => '', 'occupation' => '', 'income' => '']; ?>
            <tr>
                <td><?php echo htmlspecialchars((string) ($row['name'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['age'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['education'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['occupation'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['income'] ?? '')); ?></td>
            </tr>
        <?php endfor; ?>
    </table>

    <div class="section-title">EDUCATIONAL BACKGROUND</div>
    <table class="paper-table">
        <tr>
            <th style="width:13%">Level</th>
            <th style="width:43%">School</th>
            <th style="width:16%">Year</th>
            <th style="width:28%">Honors/Awards</th>
        </tr>
        <?php
        $defaultRows = [
            ['level' => 'Elementary', 'school' => '', 'year' => '', 'honors' => ''],
            ['level' => 'High School', 'school' => '', 'year' => '', 'honors' => ''],
            ['level' => 'College', 'school' => '', 'year' => '', 'honors' => ''],
            ['level' => 'Course', 'school' => $displayCourse, 'year' => '', 'honors' => ''],
        ];
        ?>
        <?php for ($i = 0; $i < 4; $i++): ?>
            <?php $row = is_array($educationRows[$i] ?? null) ? $educationRows[$i] : $defaultRows[$i]; ?>
            <tr>
                <td><?php echo htmlspecialchars((string) ($row['level'] ?? '')); ?></td>
                <?php if (strtolower(trim((string) ($row['level'] ?? ''))) === 'course'): ?>
                    <td colspan="3"><?php echo htmlspecialchars($naValue((string) ($row['school'] ?? $displayCourse))); ?></td>
                <?php else: ?>
                    <td><?php echo htmlspecialchars((string) ($row['school'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['year'] ?? '')); ?></td>
                    <td><?php echo htmlspecialchars((string) ($row['honors'] ?? '')); ?></td>
                <?php endif; ?>
            </tr>
        <?php endfor; ?>
    </table>

    <div class="section-title">SCHOLARSHIP GRANTS AVAILED</div>
    <table class="paper-table">
        <tr>
            <th style="width:58%">Scholarship Program</th>
            <th style="width:42%">Year/Period</th>
        </tr>
        <?php for ($i = 0; $i < 3; $i++): ?>
            <?php $row = is_array($grantsRows[$i] ?? null) ? $grantsRows[$i] : ['program' => '', 'period' => '']; ?>
            <tr>
                <td><?php echo htmlspecialchars((string) ($row['program'] ?? '')); ?></td>
                <td><?php echo htmlspecialchars((string) ($row['period'] ?? '')); ?></td>
            </tr>
        <?php endfor; ?>
    </table>

    <div style="display:block;width:2.8in;text-align:center;margin-left:auto;margin-top:18px;">
        <div style="border-bottom:1px solid #000;height:24px;position:relative;">
            <?php if ($signatureSrc !== ''): ?>
                <img
                    src="<?php echo htmlspecialchars($signatureSrc); ?>"
                    alt="Applicant signature"
                    style="position:absolute;left:50%;bottom:2px;transform:translateX(-50%);max-width:2.35in;max-height:0.55in;object-fit:contain;"
                >
            <?php endif; ?>
        </div>
        <div style="font-weight:700;margin-top:4px;">Signature of Applicant</div>
    </div>

    <table class="paper-table" style="margin-top:16px;">
        <tr>
            <td style="width:35%">
                <strong>FB PAGE:</strong><br>
                <span style="color:#1a98d5;font-weight:700;">LGU-SAN ENRIQUE SCHOLARS</span>
            </td>
            <td style="width:65%">
                <strong style="color:#ca6640;">PLEASE ATTACH THE FOLLOWING DOCUMENTS:</strong>
                <ul style="margin:3px 0 0 16px;padding:0;">
                    <?php if (!empty($documents)): ?>
                        <?php foreach ($documents as $document): ?>
                            <?php $docName = $documentLabel($document); ?>
                            <?php $isSoaDoc = stripos($docName, 'soa') !== false || stripos($docName, 'student copy') !== false || stripos($docName, 'statement of account') !== false; ?>
                            <li>
                                <?php echo htmlspecialchars($docName); ?>
                                <?php if ($isSoaDoc): ?>
                                    <span class="small-note">(Submit later upon LGU notice)</span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <li>Report Card / Previous Semester (Photocopy)</li>
                        <li>1 pc 2x2 Picture</li>
                        <li>Barangay Residency</li>
                        <li>Original Student's Copy / Statement of Account (SOA) <span class="small-note">(Submit later upon LGU notice)</span></li>
                    <?php endif; ?>
                </ul>
            </td>
        </tr>
    </table>
</div>
