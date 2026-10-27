<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\PHPStan;

use Override;
use PHPStan\Rules\Rule;
use PHPStan\Testing\RuleTestCase;
use StructuraPhp\QueryBuilder\PHPStan\ConfigurationCollector;
use StructuraPhp\QueryBuilder\PHPStan\QueryClassCollector;
use StructuraPhp\QueryBuilder\PHPStan\QueryConfigurationRule;

/**
 * @extends RuleTestCase<QueryConfigurationRule>
 */
final class ParityTest extends RuleTestCase
{
    public function testEveryRuntimeErrorHasAStaticTwin(): void
    {
        $files = array_merge(
            $this->files(__DIR__ . '/data/scope-*.php'),
            $this->files(__DIR__ . '/data/config-*.php'),
        );
        $messages = [];

        foreach ($this->gatherAnalyserErrors($files) as $error) {
            $messages[] = $error->getMessage();
        }

        $orphans = [];

        foreach ($this->runtimeFormats() as $format) {
            $pattern = '/^' . strtr(preg_quote($format, '/'), [
                '%s' => '.+',
                '%d' => '\d+',
            ]) . '$/s';

            if (preg_grep($pattern, $messages) === []) {
                $orphans[] = $format;
            }
        }

        self::assertSame(
            [],
            $orphans,
            "Runtime errors without a static twin:\n" . implode("\n", $orphans),
        );
    }

    #[Override]
    protected function getRule(): Rule
    {
        return new QueryConfigurationRule(self::createReflectionProvider());
    }

    #[Override]
    protected function getCollectors(): array
    {
        return [new QueryClassCollector(), new ConfigurationCollector()];
    }

    /**
     * @return list<string>
     */
    private function files(string $pattern): array
    {
        $files = glob($pattern);

        return $files === false ? [] : $files;
    }

    /**
     * @return list<string>
     */
    private function runtimeFormats(): array
    {
        $source = '';

        foreach ([
            ...$this->files(__DIR__ . '/../../src/Service/*.php'),
            ...$this->files(__DIR__ . '/../../src/ValueObject/Config/*.php'),
        ] as $file) {
            $source .= file_get_contents($file);
        }

        $literal = "'(?:[^'\\\\\\\\]|\\\\\\\\.)*'";
        preg_match_all(
            '/new ConfigurationException\(\s*\\\?sprintf\(\s*('
            . $literal . '(?:\s*\.\s*' . $literal . ')*)/',
            $source,
            $matches,
        );

        $formats = [];

        foreach ($matches[1] as $concatenated) {
            preg_match_all('/' . $literal . '/', $concatenated, $parts);
            $formats[] = implode('', array_map(
                static fn (string $part): string => stripcslashes(
                    substr($part, 1, -1),
                ),
                $parts[0],
            ));
        }

        self::assertNotEmpty($formats);

        return array_values(array_unique($formats));
    }
}
