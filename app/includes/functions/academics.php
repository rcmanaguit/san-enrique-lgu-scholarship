<?php
declare(strict_types=1);

function san_enrique_barangays(): array
{
    return [
        'Bagonawa',
        'Baliwagan',
        'Batuan',
        'Guintorilan',
        'Nayon',
        'Poblacion',
        'Sibucao',
        'Tabao Baybay',
        'Tabao Rizal',
        'Tibsoc',
    ];
}

function san_enrique_town(): string
{
    return 'San Enrique';
}

function san_enrique_province(): string
{
    return 'Negros Occidental';
}

function normalize_barangay(string $barangay): string
{
    $barangay = trim($barangay);
    if ($barangay === '') {
        return '';
    }

    foreach (san_enrique_barangays() as $allowed) {
        if (strcasecmp($barangay, $allowed) === 0) {
            return $allowed;
        }
    }

    return '';
}

function negros_occidental_colleges_universities(): array
{
    return [
        'Central Philippines State University',
        'Carlos Hilado Memorial State University',
        'University of St. La Salle',
        'University of Negros Occidental - Recoletos',
        'STI West Negros University',
        'Colegio San Agustin - Bacolod',
        'La Consolacion College Bacolod',
        'Riverside College, Inc.',
        'Bacolod City College',
        'Bago City College',
        'John B. Lacson Colleges Foundation - Bacolod',
        'VMA Global College and Training Centers',
        'AMA Computer College - Bacolod',
        'Asian College of Aeronautics - Bacolod',
        'La Carlota City College',
        'I-TECH College',
        'Technological University of the Philippines - Visayas',
        'Philippine Normal University - Visayas',
    ];
}

function school_name_aliases(): array
{
    return [
        'Central Philippines State University' => [
            'cpsu',
            'central philippines state university',
            'central philippine state university',
            'cpsu main',
            'cpsu kabankalan',
            'cpsu san carlos',
            'cpsu sipalay',
            'cpsu hinigaran',
            'cpsu ilog',
            'cpsu candoni',
            'cpsu cauayan',
            'cpsu moises padilla',
            'cpsu valladolid',
            'central philippines state university main campus',
            'central philippines state university kabankalan',
            'central philippines state university san carlos',
            'central philippines state university sipalay',
            'central philippines state university hinigaran',
            'central philippines state university ilog',
            'central philippines state university candoni',
            'central philippines state university cauayan',
            'central philippines state university moises padilla',
            'central philippines state university valladolid',
        ],
        'Carlos Hilado Memorial State University' => [
            'chmsu',
            'carlos hilado memorial state university',
            'carlos hilado memorial state university main',
            'chmsu alijis',
            'carlos hilado memorial state university alijis',
            'chmsu fortune towne',
            'carlos hilado memorial state university fortune towne',
            'chmsu binalbagan',
            'carlos hilado memorial state university binalbagan',
            'chmsu talisay',
            'carlos hilado memorial state university talisay',
        ],
        'University of St. La Salle' => [
            'usls',
            'university of st la salle',
            'university of saint la salle',
        ],
        'University of Negros Occidental - Recoletos' => [
            'uno-r',
            'unor',
            'uno r',
            'university of negros occidental recoletos',
        ],
        'STI West Negros University' => [
            'sti wnu',
            'sti west negros university',
            'sti west negros',
        ],
        'Colegio San Agustin - Bacolod' => [
            'csab',
            'colegio san agustin bacolod',
        ],
        'La Consolacion College Bacolod' => [
            'lccb',
            'la consolacion college bacolod',
        ],
        'Riverside College, Inc.' => [
            'rci',
            'riverside college',
            'riverside college inc',
        ],
        'Bacolod City College' => [
            'bcc',
            'bacolod city college',
        ],
        'Bago City College' => [
            'bago city college',
        ],
        'Northern Negros State College of Science and Technology' => [
            'nonescost',
            'northern negros state college of science and technology',
        ],
        'Fellowship Baptist College' => [
            'fbc',
            'fellowship baptist college',
        ],
        'John B. Lacson Colleges Foundation - Bacolod' => [
            'jblcf-bacolod',
            'jblcf bacolod',
            'john b lacson colleges foundation bacolod',
            'john b lacson bacolod',
        ],
        'VMA Global College and Training Centers' => [
            'vma global',
            'vma global college',
            'vma global college and training centers',
        ],
        'AMA Computer College - Bacolod' => [
            'ama bacolod',
            'ama computer college bacolod',
            'ama',
        ],
        'Asian College of Aeronautics - Bacolod' => [
            'aca bacolod',
            'asian college of aeronautics bacolod',
        ],
        'Abea-Binzons College' => [
            'abea binzons',
            'abea-binzons college',
        ],
        'West Negros College' => [
            'wnc',
            'west negros college',
        ],
        'Talisay City College' => [
            'tcc',
            'talisay city college',
        ],
        'Silay Institute' => [
            'silay institute',
        ],
        'State University of Northern Negros - Main Campus' => [
            'sunn main',
            'state university of northern negros',
            'state university of northern negros main',
            'sunn',
        ],
        'State University of Northern Negros - Escalante City Campus' => [
            'sunn escalante',
            'state university of northern negros escalante',
        ],
        'State University of Northern Negros - Victorias City Campus' => [
            'sunn victorias',
            'state university of northern negros victorias',
        ],
        'State University of Northern Negros - San Carlos City Campus' => [
            'sunn san carlos',
            'state university of northern negros san carlos',
        ],
        'Kabankalan Catholic College' => [
            'kcc',
            'kabankalan catholic college',
        ],
        'Colegio de Santa Rita de San Carlos, Inc.' => [
            'cssr',
            'colegio de santa rita de san carlos',
        ],
        'Colegio de Sta. Ana de Victorias' => [
            'csav',
            'colegio de sta ana de victorias',
            'colegio de santa ana de victorias',
        ],
        'Colegio de Sto. Tomas Recoletos' => [
            'cstr',
            'colegio de sto tomas recoletos',
            'colegio de santo tomas recoletos',
        ],
        'La Carlota City College' => [
            'lccc',
            'la carlota city college',
        ],
        'I-TECH College' => [
            'itech',
            'i-tech',
            'i-tech college',
            'i tech college',
            'i-tech computer education',
        ],
        'Technological University of the Philippines - Visayas' => [
            'tup visayas',
            'tup-v',
            'technological university of the philippines visayas',
        ],
        'Philippine Normal University - Visayas' => [
            'pnu visayas',
            'pnu-v',
            'philippine normal university visayas',
        ],
        'Binalbagan Catholic College' => [
            'bcc binalbagan',
            'binalbagan catholic college',
        ],
        'Southland College' => [
            'southland college',
        ],
        'Paglaum State University' => [
            'psu',
            'paglaum state university',
        ],
    ];
}

function scholarship_course_options(): array
{
    return [
        'BS Information Technology',
        'BS Computer Science',
        'BS Information Systems',
        'BS Accountancy',
        'BS Business Administration',
        'BS Hospitality Management',
        'BS Tourism Management',
        'BS Secondary Education',
        'BS Elementary Education',
        'BS Psychology',
        'BS Criminology',
        'BS Nursing',
        'BS Midwifery',
        'BS Medical Technology',
        'BS Civil Engineering',
        'BS Mechanical Engineering',
        'BS Electrical Engineering',
        'BS Industrial Engineering',
        'BS Agriculture',
        'BS Agribusiness',
        'BS Fisheries',
    ];
}

function format_title_case_text(string $value): string
{
    $value = strtolower($value);
    return preg_replace_callback('/[a-z]+/i', static function (array $matches): string {
        $word = (string) ($matches[0] ?? '');
        if ($word === '') {
            return $word;
        }
        return strtoupper(substr($word, 0, 1)) . substr($word, 1);
    }, $value) ?? $value;
}

function validate_typed_academic_text(string $value, string $label, int $minLength = 3, int $maxLength = 120): array
{
    $value = trim((string) $value);
    $value = preg_replace('/\s+/', ' ', $value) ?? $value;

    if ($value === '') {
        return ['ok' => false, 'value' => '', 'error' => $label . ' is required.'];
    }

    $length = strlen($value);
    if ($length < $minLength || $length > $maxLength) {
        return ['ok' => false, 'value' => '', 'error' => $label . ' must be between ' . $minLength . ' and ' . $maxLength . ' characters.'];
    }

    if (preg_match("/^[A-Za-z0-9 .,&()'\\/-]+$/", $value) !== 1) {
        return ['ok' => false, 'value' => '', 'error' => $label . ' contains invalid characters.'];
    }

    if (preg_match("/[.,&()'\\/-]{2,}/", $value) === 1) {
        return ['ok' => false, 'value' => '', 'error' => $label . ' has repeated symbols.'];
    }

    if (preg_match('/[A-Za-z]/', $value) !== 1) {
        return ['ok' => false, 'value' => '', 'error' => $label . ' must include letters.'];
    }

    $formatted = format_title_case_text($value);
    return ['ok' => true, 'value' => $formatted, 'error' => ''];
}

function normalize_course_name(string $courseName): string
{
    $courseName = trim($courseName);
    if ($courseName === '') {
        return '';
    }

    return preg_replace('/\s+/', ' ', $courseName) ?? $courseName;
}

function is_valid_scholarship_course(string $courseName): bool
{
    $courseName = trim($courseName);
    if ($courseName === '') {
        return false;
    }

    foreach (scholarship_course_options() as $allowed) {
        if (strcasecmp($courseName, $allowed) === 0) {
            return true;
        }
    }
    return false;
}

function normalize_school_name(string $schoolName): string
{
    $schoolName = trim($schoolName);
    if ($schoolName === '') {
        return '';
    }

    $schoolName = preg_replace('/\s+/', ' ', $schoolName) ?? $schoolName;
    $key = strtolower((string) (preg_replace('/[^a-z0-9]+/', '', $schoolName) ?? ''));

    foreach (school_name_aliases() as $canonical => $aliases) {
        $candidateKeys = array_merge([$canonical], $aliases);
        foreach ($candidateKeys as $candidate) {
            $candidateKey = strtolower((string) (preg_replace('/[^a-z0-9]+/', '', (string) $candidate) ?? ''));
            if ($candidateKey !== '' && $candidateKey === $key) {
                return $canonical;
            }
        }
    }

    return $schoolName;
}

function is_valid_negros_occidental_school_name(string $schoolName): bool
{
    $schoolName = trim($schoolName);
    if ($schoolName === '') {
        return false;
    }

    foreach (negros_occidental_colleges_universities() as $allowed) {
        if (strcasecmp($schoolName, $allowed) === 0) {
            return true;
        }
    }
    return false;
}

function is_valid_barangay(string $barangay): bool
{
    return normalize_barangay($barangay) !== '';
}

function calculate_age_from_birth_date(?string $birthDate): ?int
{
    $birthDate = trim((string) $birthDate);
    if ($birthDate === '') {
        return null;
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
    if (!$date || $date->format('Y-m-d') !== $birthDate) {
        return null;
    }

    $today = new DateTimeImmutable('today');
    if ($date > $today) {
        return null;
    }

    return $date->diff($today)->y;
}

