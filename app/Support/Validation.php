<?php

namespace App\Support;

use finfo;
use DateTimeImmutable;

class Validation
{
    public static function requiredString(mixed $value, string $field, int $maxLength = 255): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            throw new ValidationException($field . ' is required.');
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new ValidationException($field . ' is too long.');
        }

        return $normalized;
    }

    public static function optionalString(mixed $value, int $maxLength = 255): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return null;
        }

        if (mb_strlen($normalized) > $maxLength) {
            throw new ValidationException('A submitted value is too long.');
        }

        return $normalized;
    }

    public static function phone(mixed $value): string
    {
        $digits = preg_replace('/\D+/', '', (string) $value) ?? '';

        if (!preg_match('/^09\d{9}$/', $digits)) {
            throw new ValidationException('Please enter a valid 11-digit mobile number.');
        }

        return $digits;
    }

    public static function email(mixed $value): string
    {
        $normalized = trim((string) $value);

        if ($normalized === '' || !filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            throw new ValidationException('Please enter a valid email address.');
        }

        if (mb_strlen($normalized) > 150) {
            throw new ValidationException('Email address is too long.');
        }

        return mb_strtolower($normalized);
    }

    public static function password(mixed $value): string
    {
        $password = (string) $value;

        if (strlen($password) < 8) {
            throw new ValidationException('Password must be at least 8 characters long.');
        }

        if (strlen($password) > 255) {
            throw new ValidationException('Password is too long.');
        }

        return $password;
    }

    public static function otp(mixed $value): string
    {
        $otp = trim((string) $value);

        if (!preg_match('/^\d{6}$/', $otp)) {
            throw new ValidationException('OTP code must be 6 digits.');
        }

        return $otp;
    }

    public static function enum(mixed $value, array $allowed, string $field): string
    {
        $normalized = trim((string) $value);

        if (!in_array($normalized, $allowed, true)) {
            throw new ValidationException('Invalid ' . strtolower($field) . ' selected.');
        }

        return $normalized;
    }

    public static function date(mixed $value, string $field): string
    {
        $normalized = trim((string) $value);
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $normalized);
        $errors = DateTimeImmutable::getLastErrors();
        $warningCount = is_array($errors) ? (int) ($errors['warning_count'] ?? 0) : 0;
        $errorCount = is_array($errors) ? (int) ($errors['error_count'] ?? 0) : 0;

        if (!$date || $warningCount > 0 || $errorCount > 0 || $date->format('Y-m-d') !== $normalized) {
            throw new ValidationException('Invalid ' . strtolower($field) . '.');
        }

        return $normalized;
    }

    public static function nonNegativeDecimal(mixed $value, string $field, bool $allowNull = true): ?string
    {
        $normalized = trim((string) $value);

        if ($normalized === '') {
            return $allowNull ? null : '0.00';
        }

        if (!is_numeric($normalized) || (float) $normalized < 0) {
            throw new ValidationException($field . ' must be a valid non-negative amount.');
        }

        return number_format((float) $normalized, 2, '.', '');
    }

    public static function fileUpload(array $file, string $field, array $allowedMimeTypes, int $maxBytes): string
    {
        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw new ValidationException(self::fileUploadErrorMessage($field, $errorCode));
        }

        $tmpName = (string) ($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new ValidationException('Invalid ' . strtolower($field) . ' upload.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            throw new ValidationException($field . ' must not exceed 2MB.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = (string) $finfo->file($tmpName);

        if (!array_key_exists($mimeType, $allowedMimeTypes)) {
            throw new ValidationException($field . ' must be a JPG, PNG, or PDF file.');
        }

        return $allowedMimeTypes[$mimeType];
    }

    private static function fileUploadErrorMessage(string $field, int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_NO_FILE => $field . ' upload is required.',
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => $field . ' must not exceed 2MB.',
            UPLOAD_ERR_PARTIAL => $field . ' upload was incomplete. Please try again.',
            default => 'Unable to upload ' . strtolower($field) . '. Please try again.',
        };
    }

    public static function base64Image(mixed $value, string $field, array $allowedExtensions): array
    {
        $normalized = trim((string) $value);
        if ($normalized === '') {
            throw new ValidationException($field . ' is required.');
        }

        $normalized = preg_replace('/\s+/', '', $normalized) ?? '';
        if ($normalized === '') {
            throw new ValidationException($field . ' is required.');
        }

        if (!preg_match('/^data:image\/([a-zA-Z0-9.+-]+);base64,/i', $normalized, $matches)) {
            throw new ValidationException($field . ' format is invalid.');
        }

        $declaredType = strtolower((string) $matches[1]);
        $binaryPayload = substr($normalized, strpos($normalized, ',') + 1);
        $binaryPayload = str_replace(' ', '+', $binaryPayload);

        $binary = base64_decode($binaryPayload, true);
        if ($binary === false || $binary === '') {
            throw new ValidationException($field . ' could not be processed.');
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = strtolower((string) $finfo->buffer($binary));
        $mimeToExtension = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/jpg' => 'jpg',
        ];
        $extension = $mimeToExtension[$mimeType] ?? null;

        if ($extension === null) {
            throw new ValidationException($field . ' format is invalid.');
        }

        if ($declaredType === 'jpeg') {
            $declaredType = 'jpg';
        }

        if ($declaredType !== $extension) {
            throw new ValidationException($field . ' format is invalid.');
        }

        if (!in_array($extension, $allowedExtensions, true)) {
            throw new ValidationException($field . ' format is not supported.');
        }

        if (strlen($binary) > 2 * 1024 * 1024) {
            throw new ValidationException($field . ' must not exceed 2MB.');
        }

        return [
            'extension' => $extension,
            'binary' => $binary,
        ];
    }
}
