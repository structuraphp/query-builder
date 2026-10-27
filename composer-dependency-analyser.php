<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

// The PHPStan rules only run inside PHPStan, which bundles PhpParser.
return (new Configuration())
    ->ignoreErrorsOnPackageAndPath(
        'phpstan/phpstan',
        __DIR__ . '/src/PHPStan',
        [ErrorType::DEV_DEPENDENCY_IN_PROD],
    )
    ->ignoreErrorsOnPackageAndPaths(
        'nikic/php-parser',
        [__DIR__ . '/src/PHPStan', __DIR__ . '/tests/PHPStan'],
        [ErrorType::SHADOW_DEPENDENCY],
    );
