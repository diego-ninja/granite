<?php

// ABOUTME: Checks that every production PHP file declares its purpose.
// ABOUTME: Keeps the source-purpose convention enforced independently of Pint.

declare(strict_types=1);

$sourceDirectory = dirname(__DIR__) . '/src';
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($sourceDirectory, FilesystemIterator::SKIP_DOTS),
);
$violations = [];

foreach ($iterator as $file) {
    if ('php' !== $file->getExtension()) {
        continue;
    }

    $lines = file($file->getPathname(), FILE_IGNORE_NEW_LINES);
    if (false === $lines
        || '<?php' !== ($lines[0] ?? null)
        || ! str_starts_with($lines[1] ?? '', '// ABOUTME:')
        || ! str_starts_with($lines[2] ?? '', '// ABOUTME:')
        || str_starts_with($lines[3] ?? '', '// ABOUTME:')) {
        $violations[] = $file->getPathname();
    }
}

if ([] !== $violations) {
    fwrite(STDERR, "Invalid ABOUTME headers:\n" . implode("\n", $violations) . "\n");
    exit(1);
}

fwrite(STDOUT, "ABOUTME headers valid for production sources.\n");
