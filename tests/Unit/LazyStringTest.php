<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use ArrayObject;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use StructuraPhp\QueryBuilder\ValueObject\LazyString;

#[CoversClass(LazyString::class)]
final class LazyStringTest extends TestCase
{
    public function testResolvesOnlyWhenCastToString(): void
    {
        /** @var ArrayObject<int, string> $spy */
        $spy = new ArrayObject();

        $lazy = new LazyString(static function () use ($spy): string {
            $spy[] = 'called';

            return 'Hello';
        });

        self::assertCount(0, $spy);
        self::assertSame('Hello', (string) $lazy);
        self::assertCount(1, $spy);
    }

    public function testResolvesOnce(): void
    {
        /** @var ArrayObject<int, string> $spy */
        $spy = new ArrayObject();

        $lazy = new LazyString(static function () use ($spy): string {
            $spy[] = 'called';

            return 'Hello ' . \count($spy);
        });

        self::assertSame('Hello 1', (string) $lazy);
        self::assertSame('Hello 1', (string) $lazy);
        self::assertCount(1, $spy);
    }

    public function testKeepsAnEmptyResult(): void
    {
        /** @var ArrayObject<int, string> $spy */
        $spy = new ArrayObject();

        $lazy = new LazyString(static function () use ($spy): string {
            $spy[] = 'called';

            return '';
        });

        self::assertSame('', (string) $lazy);
        self::assertSame('', (string) $lazy);
        self::assertCount(1, $spy);
    }

    public function testOfKeepsAStringAsIs(): void
    {
        self::assertSame('Plain', LazyString::of('Plain'));
    }

    public function testOfWrapsAClosure(): void
    {
        $lazy = LazyString::of(static fn (): string => 'Deferred');

        self::assertInstanceOf(LazyString::class, $lazy);
        self::assertSame('Deferred', (string) $lazy);
    }
}
