<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

// PHPStan restores the file stream wrapper when it finds a class through the
// autoloader, and so drops the one Infection loads its mutants through: load
// the rules before any analysis.
foreach (new FilesystemIterator(__DIR__ . '/../src/PHPStan') as $file) {
    /** @var SplFileInfo $file */
    $name = $file->getBasename('.php');
    class_exists('StructuraPhp\QueryBuilder\PHPStan\\' . $name);
}
