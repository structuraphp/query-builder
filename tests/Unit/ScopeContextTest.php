<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use StructuraPhp\QueryBuilder\Contract\Config\ScopeInterface;
use StructuraPhp\QueryBuilder\ValueObject\QueryParameters;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Author;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Queries\ConfigurableQuery;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Status;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class ScopeContextTest extends TestCase
{
    private Author $alice;

    private Author $bob;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alice = Author::query()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'password' => 'secret',
        ]);
        $this->bob = Author::query()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'password' => 'secret',
        ]);

        DB::table('articles')->insert([
            ['title' => 'Foo', 'status' => 'draft', 'author_id' => 1],
            ['title' => 'Bar', 'status' => 'published', 'author_id' => 1],
            ['title' => 'Baz', 'status' => 'draft', 'author_id' => 2],
        ]);
    }

    public function testContextIsInjectedNextToUrlArguments(): void
    {
        $titles = $this->titles(
            ['ownedBy' => ['status' => 'draft']],
            ['ownedBy' => ['author' => $this->bob]],
        );

        self::assertSame(['Baz'], $titles);
    }

    public function testContextArgumentIsNotReadFromTheUrl(): void
    {
        $titles = $this->titles(
            ['ownedBy' => ['status' => 'draft', 'author' => 2]],
            ['ownedBy' => ['author' => $this->alice]],
        );

        self::assertSame(['Foo'], $titles);
    }

    public function testContextOnlyScopeIsABooleanToggle(): void
    {
        $context = ['among' => ['ids' => [1, 3]]];

        $enabled = $this->titles(['among' => '1'], $context);
        $disabled = $this->titles(['among' => '0'], $context);

        self::assertSame(['Foo', 'Baz'], $enabled);
        self::assertSame(['Foo', 'Bar', 'Baz'], $disabled);
    }

    public function testContextClosureIsResolvedWhenRequested(): void
    {
        $titles = $this->titles(
            ['among' => '1'],
            ['among' => ['ids' => fn (): array => [2]]],
        );

        self::assertSame(['Bar'], $titles);
    }

    public function testContextClosureIsSkippedWhenNotRequested(): void
    {
        $fail = fn () => throw new RuntimeException();

        $titles = $this->titles([], ['among' => ['ids' => $fail]]);

        self::assertSame(['Foo', 'Bar', 'Baz'], $titles);
    }

    public function testContextClosureReceivesCastRequestArguments(): void
    {
        $ids = fn (Status $status): array => $status === Status::Draft
            ? [2, 3]
            : [];

        $titles = $this->titles(
            ['ofStatusAmong' => ['status' => 'draft']],
            ['ofStatusAmong' => ['ids' => $ids]],
        );

        self::assertSame(['Baz'], $titles);
    }

    public function testContextClosureAskingAnUnknownArgumentThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->titles(
            ['ofStatusAmong' => ['status' => 'draft']],
            ['ofStatusAmong' => ['ids' => fn (string $foo): array => []]],
        );
    }

    public function testClosureTypedArgumentReceivesTheClosure(): void
    {
        $callback = function (Builder $query): void {
            $query->where('title', 'Bar');
        };

        $titles = $this->titles(
            ['tapped' => '1'],
            ['tapped' => ['callback' => $callback]],
        );

        self::assertSame(['Bar'], $titles);
    }

    public function testConfigDefaultReplacesMissingContext(): void
    {
        $scope = function (ScopeInterface $scope): void {
            $scope->scope('among')->context('ids')->default(ids: [3]);
        };

        $titles = $this->titles(['among' => '1'], [], $scope);

        self::assertSame(['Baz'], $titles);
    }

    public function testMissingContextThrows(): void
    {
        $this->expectException(LogicException::class);

        $this->titles(['among' => '1']);
    }

    public function testUnknownContextScopeThrows(): void
    {
        $this->expectException(LogicException::class);

        $this->titles([], ['missing' => ['ids' => [1]]]);
    }

    public function testContextForAnUrlArgumentThrows(): void
    {
        $this->expectException(LogicException::class);

        $this->titles([], ['ownedBy' => ['status' => 'draft']]);
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, array<string, mixed>> $context
     * @param null|(Closure(ScopeInterface): void) $scope
     *
     * @return list<mixed>
     */
    private function titles(
        array $input,
        array $context = [],
        ?Closure $scope = null,
    ): array {
        $scope ??= function (ScopeInterface $scope): void {
            $scope->scope('ownedBy')->context('author');
            $scope->scope('among')->context('ids');
            $scope->scope('tapped')->context('callback');
            $scope->scope('ofStatusAmong')->context('ids');
        };

        $parameters = QueryParameters::fromArray(
            new ConfigurableQuery(scope: $scope),
            $input,
        );

        $query = $parameters->builder(context: $context);
        $query->getQuery()->orderBy('id');

        return array_values($query->pluck('title')->all());
    }
}
