<?php

declare(strict_types=1);

/**
 * @see https://mlocati.github.io/php-cs-fixer-configurator
 */

use PhpCsFixer\Config;
use PhpCsFixer\Finder;
use PhpCsFixer\Runner\Parallel\ParallelConfig;

$finder = Finder::create()
    ->in([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ])
    ->append([
        __DIR__ . '/rector.php',
        __FILE__,
    ]);

$config = new Config();

return $config
    ->setCacheFile(__DIR__ . '/.cache/php-cs-fixer.cache')
    ->setRules([
        '@PhpCsFixer' => true,
        // Added rules
        'declare_strict_types' => true,
        'phpdoc_line_span' => ['property' => 'single', 'const' => 'single'],
        'static_lambda' => true,

        // Override @PhpCsFixer
        // Lines stay under 80 characters: a long `implements` list wraps.
        'class_definition' => [
            'multi_line_extends_each_single_line' => true,
            'single_line' => false,
        ],
        'concat_space' => ['spacing' => 'one'],
        'global_namespace_import' => [
            'import_constants' => false,
            'import_functions' => false,
        ],
        'heredoc_to_nowdoc' => false,
        'increment_style' => ['style' => 'post'],
        'multiline_whitespace_before_semicolons' => [
            'strategy' => 'no_multi_line',
        ],
        'ordered_imports' => [
            'imports_order' => [
                'class',
                'function',
                'const',
            ],
            'sort_algorithm' => 'alpha',
        ],
        'php_unit_internal_class' => false,
        // Rector turns `@coversNothing` into `#[CoversNothing]`, which this
        // rule does not recognise.
        'php_unit_test_class_requires_covers' => false,
        'return_assignment' => false,
        'phpdoc_to_comment' => ['ignored_tags' => ['var']],
        'phpdoc_align' => ['align' => 'left'],
        'trailing_comma_in_multiline' => [
            'elements' => [
                'arguments',
                'array_destructuring',
                'arrays',
                'match',
                'parameters',
            ],
        ],
        'yoda_style' => false,
    ])
    ->setParallelConfig(new ParallelConfig(6, 20))
    ->setRiskyAllowed(true)
    ->setFinder($finder);
