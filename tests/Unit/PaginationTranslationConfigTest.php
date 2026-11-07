<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use StructuraPhp\QueryBuilder\ValueObject\Config\Pagination;

#[CoversClass(Pagination::class)]
final class PaginationTranslationConfigTest extends TestCase
{
    public function testMessagesAreAddedPerParameter(): void
    {
        $pagination = new Pagination();

        $pagination
            ->messages(limit: ['max' => 'High.'])
            ->messages(limit: ['min' => 'Low.'], page: ['min' => 'First.']);

        self::assertSame(
            [
                'pagination' => [],
                'limit' => ['max' => 'High.', 'min' => 'Low.'],
                'page' => ['min' => 'First.'],
                'cursor' => [],
            ],
            $pagination->messages,
        );
    }

    public function testASecondCallReplacesTheSameRule(): void
    {
        $pagination = new Pagination();

        $pagination
            ->messages(limit: ['max' => 'Old.'])
            ->messages(limit: ['max' => 'New.']);

        self::assertSame(['max' => 'New.'], $pagination->messages['limit']);
    }

    public function testAttributesAreAddedPerParameter(): void
    {
        $pagination = new Pagination();

        $pagination->attribute(limit: 'size')->attribute(page: 'number');

        self::assertSame(
            ['limit' => 'size', 'page' => 'number'],
            $pagination->attributes,
        );
    }

    public function testAnAttributeNotGivenIsNotRecorded(): void
    {
        $pagination = new Pagination();

        $pagination->attribute(cursor: 'bookmark');

        self::assertSame(['cursor' => 'bookmark'], $pagination->attributes);
    }
}
