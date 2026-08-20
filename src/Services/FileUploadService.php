<?php

declare(strict_types=1);

namespace App\Services;

use App\Support\ValidationException;

final class FileUploadService
{
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    private const MAX_BYTES = 4 * 1024 * 1024; // 4MB

    public static function storeProductImage(array $file): string
    {
        return self::storeImage($file, 'products');
    }

    public static function storeAdImage(array $file): string
    {
        return self::storeImage($file, 'ads');
    }

    private static function storeImage(array $file, string $subdir): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            throw new ValidationException(['image' => 'No file was uploaded.']);
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException(['image' => 'Upload failed. Please try again.']);
        }
        if ($file['size'] > self::MAX_BYTES) {
            throw new ValidationException(['image' => 'Image must be smaller than 4MB.']);
        }
        if (!is_uploaded_file($file['tmp_name'])) {
            throw new ValidationException(['image' => 'Invalid upload.']);
        }

        $mime = mime_content_type($file['tmp_name']);
        if (!isset(self::ALLOWED_MIME[$mime])) {
            throw new ValidationException(['image' => 'Only JPG, PNG, WebP, or GIF images are allowed.']);
        }

        // Re-encode via GD so embedded payloads (polyglot files, EXIF scripts) can't
        // survive as anything other than pixel data, and never trust the client filename.
        $extension = self::ALLOWED_MIME[$mime];
        $filename = bin2hex(random_bytes(16)) . '.' . $extension;
        $uploadDir = base_path('public/uploads/' . $subdir);
        $destination = $uploadDir . '/' . $filename;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        // mkdir()'s mode above is filtered through the process umask, so on hosts
        // with a restrictive umask (common on shared hosting) the directory can
        // end up non-traversable by the web server user even though PHP itself
        // can still write to it - chmod explicitly rather than trusting mkdir's
        // requested mode to survive the umask.
        @chmod($uploadDir, 0755);
        if (!is_writable($uploadDir)) {
            throw new ValidationException(['image' => "The server cannot write to the uploads folder. Ask your host to fix the folder permissions on public/uploads/{$subdir}."]);
        }

        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
            'image/png' => imagecreatefrompng($file['tmp_name']),
            'image/webp' => imagecreatefromwebp($file['tmp_name']),
            'image/gif' => imagecreatefromgif($file['tmp_name']),
        };

        if ($image === false) {
            throw new ValidationException(['image' => 'Could not process this image.']);
        }

        $written = match ($extension) {
            'jpg' => imagejpeg($image, $destination, 88),
            'png' => imagepng($image, $destination, 6),
            'webp' => imagewebp($image, $destination, 88),
            'gif' => imagegif($image, $destination),
        };
        imagedestroy($image);

        if (!$written || !is_file($destination)) {
            throw new ValidationException(['image' => 'Failed to save the uploaded image. Please try again.']);
        }

        // Same umask problem as the directory above: imagejpeg()/imagepng()/etc.
        // create the file using the process's default mode minus the umask, which
        // on a restrictive host can come out non-world-readable - the upload
        // "succeeds" (PHP can read/write its own file) but the web server process
        // serving it back to visitors gets permission-denied, rendering as a
        // broken image on every single page that shows it. Force it explicitly.
        @chmod($destination, 0644);

        return '/uploads/' . $subdir . '/' . $filename;
    }
}
