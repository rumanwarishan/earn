<?php

declare(strict_types=1);

// One-time repair for uploaded images that were saved before FileUploadService
// started forcing explicit file/directory permissions. On hosts with a
// restrictive umask, GD's imagejpeg()/imagepng()/etc. can leave a file that
// PHP itself can read/write but the web server's own user cannot serve back
// to visitors - it exists on disk, the database row is correct, but every
// visitor sees a broken image. Run this once after deploying the fix to
// repair anything already uploaded:
//
//   php bin/fix-upload-perms.php
//
// Safe to run any number of times.

require dirname(__DIR__) . '/bootstrap/app.php';

$root = base_path('public/uploads');
$fixed = 0;
$scanned = 0;

if (!is_dir($root)) {
    fwrite(STDERR, "No public/uploads directory found at {$root}\n");
    exit(1);
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $scanned++;
    if ($item->isDir()) {
        if (@chmod($item->getPathname(), 0755)) {
            $fixed++;
        }
    } else {
        if (@chmod($item->getPathname(), 0644)) {
            $fixed++;
        }
    }
}

@chmod($root, 0755);

echo "Scanned {$scanned} item(s) under public/uploads, fixed permissions on {$fixed}.\n";
echo "If images still don't load after this, the issue is elsewhere (missing files, wrong document root, or a CDN/cache) - not permissions.\n";
