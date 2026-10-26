<?php

declare(strict_types=1);

namespace Tests\StructuraPhp\QueryBuilder\Unit;

use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Enum;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use stdClass;
use StructuraPhp\QueryBuilder\Enum\Type;
use StructuraPhp\QueryBuilder\Exception\ConfigurationException;
use StructuraPhp\QueryBuilder\Service\ScopeReflector;
use StructuraPhp\QueryBuilder\ValueObject\Config\ScopeConfig;
use StructuraPhp\QueryBuilder\ValueObject\ScopeArgument;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Article;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ScopePriority;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ScopeTypedArticle;
use Tests\StructuraPhp\QueryBuilder\Fixtures\ScopeVersion;
use Tests\StructuraPhp\QueryBuilder\Fixtures\Status;
use Tests\StructuraPhp\QueryBuilder\TestCase;

final class ScopeReflectorTest extends TestCase
{
    public function testNoArgumentScope(): void
    {
        $arguments = $this->arguments(new ScopeConfig('popular'));

        self::assertSame([], $arguments);
    }

    public function testDateArguments(): void
    {
        [$from, $to] = $this->arguments(new ScopeConfig('publishedBetween'));
        $cast = ($from->cast)('2024-01-02');

        self::assertSame('from', $from->name);
        self::assertSame('to', $to->name);
        self::assertSame(['date_format:Y-m-d'], $from->rules);
        self::assertNull($from->default);
        self::assertInstanceOf(CarbonImmutable::class, $cast);
    }

    public function testEnumArgument(): void
    {
        [$status] = $this->arguments(new ScopeConfig('ofStatus'));
        $cast = ($status->cast)('draft');

        self::assertInstanceOf(Enum::class, $status->rules[0]);
        self::assertSame(Status::Draft, $cast);
    }

    public function testArrayArgument(): void
    {
        [$statuses] = $this->arguments(new ScopeConfig('ofStatuses'));
        $cast = ($statuses->cast)(['draft']);

        self::assertSame(['array'], $statuses->rules);
        self::assertSame(['draft'], $cast);
    }

    public function testMutableCarbonArgument(): void
    {
        [$date] = $this->arguments(new ScopeConfig('since'));
        $cast = ($date->cast)('2024-01-02');

        self::assertInstanceOf(Carbon::class, $cast);
    }

    public function testAttributeScopeAndPhpDefault(): void
    {
        [$min] = $this->arguments(new ScopeConfig('minViews'));
        $default = $min->default instanceof Closure ? ($min->default)() : null;
        $cast = ($min->cast)('12');

        self::assertSame(['integer'], $min->rules);
        self::assertSame(10, $default);
        self::assertSame(12, $cast);
    }

    public function testConfigDefaultWinsOverPhpDefault(): void
    {
        $config = new ScopeConfig('minViews')
            ->default(min: static fn (): int => 50);

        [$min] = $this->arguments($config);
        $default = $min->default instanceof Closure ? ($min->default)() : null;

        self::assertSame(50, $default);
    }

    public function testFixedConfigDefault(): void
    {
        $config = new ScopeConfig('publishedBetween')->default(to: 'fixed');

        [, $to] = $this->arguments($config);
        $default = $to->default instanceof Closure ? ($to->default)() : null;

        self::assertSame('fixed', $default);
    }

    public function testMutableDateDefaultIsConverted(): void
    {
        $config = new ScopeConfig('publishedBetween')->default(
            to: static fn (): CarbonInterface => now(),
        );

        [, $to] = $this->arguments($config);
        $default = $to->default instanceof Closure ? ($to->default)() : null;

        self::assertInstanceOf(CarbonImmutable::class, $default);
    }

    public function testMethodAlias(): void
    {
        $config = new ScopeConfig('statut')->method('ofStatus');

        [$status] = $this->arguments($config);

        self::assertSame('status', $status->name);
    }

    public function testForcedCast(): void
    {
        $config = new ScopeConfig('near')->cast(
            lat: Type::Float,
            lng: Type::Float,
        );

        [$lat] = $this->arguments($config);
        $cast = ($lat->cast)('1.5');

        self::assertSame(['numeric'], $lat->rules);
        self::assertSame(1.5, $cast);
    }

    public function testForcedEnumCast(): void
    {
        $config = new ScopeConfig('near')->cast(
            lat: Status::class,
            lng: Type::Float,
        );

        [$lat] = $this->arguments($config);
        $cast = ($lat->cast)('published');

        self::assertSame(Status::Published, $cast);
    }

    public function testCustomRulesEachMessagesAttribute(): void
    {
        $config = new ScopeConfig('ofStatuses')
            ->rules(statuses: ['max:2'])
            ->each(statuses: ['in:draft,published'])
            ->messages(statuses: ['max' => 'Trop.'])
            ->attribute(statuses: 'statuts');

        [$statuses] = $this->arguments($config);

        self::assertSame(['array', 'max:2'], $statuses->rules);
        self::assertSame(['in:draft,published'], $statuses->each);
        self::assertSame(['max' => 'Trop.'], $statuses->messages);
        self::assertSame('statuts', $statuses->attribute);
    }

    public function testClosureCastIsCheckedAfterTheRules(): void
    {
        $config = new ScopeConfig('near')->cast(
            lat: static fn (mixed $v) => throw new RuntimeException(),
            lng: Type::Float,
        );

        [$lat, $lng] = $this->arguments($config);

        self::assertSame([], $lat->rules);
        self::assertInstanceOf(Closure::class, $lat->check);
        self::assertNull($lng->check);
    }

    public function testContextArgument(): void
    {
        $config = new ScopeConfig('ownedBy')->context('author');

        [$status, $author] = $this->arguments($config);

        self::assertFalse($status->context);
        self::assertTrue($author->context);
        self::assertSame([], $author->rules);
    }

    public function testBooleanArgument(): void
    {
        [$featured] = $this->arguments(
            new ScopeConfig('featured'),
            new ScopeTypedArticle(),
        );

        self::assertSame(['boolean'], $featured->rules);
        self::assertTrue(($featured->cast)('1'));
    }

    public function testFloatArgument(): void
    {
        [$rating] = $this->arguments(
            new ScopeConfig('ratedAbove'),
            new ScopeTypedArticle(),
        );

        self::assertSame(['numeric'], $rating->rules);
        self::assertSame(1.5, ($rating->cast)('1.5'));
    }

    public function testIntBackedEnumArgumentCastsTheString(): void
    {
        [$priority] = $this->arguments(
            new ScopeConfig('ofPriority'),
            new ScopeTypedArticle(),
        );

        self::assertSame(ScopePriority::High, ($priority->cast)('2'));
    }

    public function testStringBackedEnumArgumentCastsAnInteger(): void
    {
        [$version] = $this->arguments(
            new ScopeConfig('ofVersion'),
            new ScopeTypedArticle(),
        );

        self::assertSame(ScopeVersion::Two, ($version->cast)(2));
    }

    public function testBuilderMethodReceivesEveryArgument(): void
    {
        $arguments = $this->arguments(
            new ScopeConfig('viewsBetween'),
            new ScopeTypedArticle(),
        );
        $names = array_map(
            static fn (ScopeArgument $argument): string => $argument->name,
            $arguments,
        );

        self::assertSame(['min', 'max'], $names);
    }

    public function testDateDefaultOfTheExpectedClassIsKept(): void
    {
        $date = CarbonImmutable::parse('2024-01-02');
        $config = new ScopeConfig('publishedBetween')->default(to: $date);

        [, $to] = $this->arguments($config);
        $default = $to->default instanceof Closure ? ($to->default)() : null;

        self::assertSame($date, $default);
    }

    public function testContextNamesAddUpOverSeveralCalls(): void
    {
        $config = new ScopeConfig('ownedBy')
            ->context('status')
            ->context('author');

        [$status, $author] = $this->arguments($config);

        self::assertTrue($status->context);
        self::assertTrue($author->context);
    }

    public function testSeveralContextNamesInOneCall(): void
    {
        $config = new ScopeConfig('ownedBy')->context('status', 'author');

        [$status, $author] = $this->arguments($config);

        self::assertTrue($status->context);
        self::assertTrue($author->context);
    }

    public function testContextNamesSpreadFromAMapAreAllKept(): void
    {
        $config = new ScopeConfig('ownedBy')
            ->context(...['name' => 'status'])
            ->context(...['name' => 'author']);

        [$status, $author] = $this->arguments($config);

        self::assertTrue($status->context);
        self::assertTrue($author->context);
    }

    public function testCastsAddUpOverSeveralCalls(): void
    {
        $config = new ScopeConfig('near')
            ->cast(lat: Type::Float)
            ->cast(lng: Type::Integer);

        [$lat, $lng] = $this->arguments($config);

        self::assertSame(['numeric'], $lat->rules);
        self::assertSame(['integer'], $lng->rules);
    }

    public function testOptionsAddUpOverSeveralCalls(): void
    {
        $config = new ScopeConfig('matching')
            ->default(ids: [1])
            ->default(titles: ['Foo'])
            ->rules(ids: ['max:2'])
            ->rules(titles: ['max:3'])
            ->each(ids: ['integer'])
            ->each(titles: ['string'])
            ->nested(ids: ['*' => ['integer']])
            ->nested(titles: ['*' => ['string']])
            ->messages(ids: ['max' => 'Ids.'])
            ->messages(titles: ['max' => 'Titles.'])
            ->attribute(ids: 'identifiants')
            ->attribute(titles: 'titres');

        [$ids, $titles] = $this->arguments($config, new ScopeTypedArticle());

        self::assertInstanceOf(Closure::class, $ids->default);
        self::assertSame(['Foo'], $titles->default instanceof Closure
            ? ($titles->default)()
            : null);
        self::assertSame(['array', 'max:2'], $ids->rules);
        self::assertSame(['array', 'max:3'], $titles->rules);
        self::assertSame(['integer'], $ids->each);
        self::assertSame(['string'], $titles->each);
        self::assertSame(['*' => ['integer']], $ids->nested);
        self::assertSame(['*' => ['string']], $titles->nested);
        self::assertSame(['max' => 'Ids.'], $ids->messages);
        self::assertSame(['max' => 'Titles.'], $titles->messages);
        self::assertSame('identifiants', $ids->attribute);
        self::assertSame('titres', $titles->attribute);
    }

    public function testNestedAttributesOfAnArgumentAddUp(): void
    {
        $config = new ScopeConfig('inRanges')
            ->attribute(ranges: ['*.min' => 'minimum'])
            ->attribute(ranges: ['*.max' => 'maximum']);

        [$ranges] = $this->arguments($config);

        self::assertSame(
            ['*.min' => 'minimum', '*.max' => 'maximum'],
            $ranges->nestedAttributes,
        );
    }

    /**
     * @param Closure(): ScopeConfig $config
     */
    #[DataProvider('invalidConfigs')]
    public function testConfigErrors(Closure $config, string $message): void
    {
        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->arguments($config());
    }

    /**
     * Configs are built lazily: some of them throw while being declared.
     *
     * @return iterable<string, array{Closure(): ScopeConfig, string}>
     */
    public static function invalidConfigs(): iterable
    {
        $untyped = 'needs a scalar, enum, date or array type, or an'
            . ' explicit cast().';
        $context = 'comes from the context: it takes no';

        yield 'unknown scope' => [
            static fn (): ScopeConfig => new ScopeConfig('missing'),
            'Scope "missing" does not exist on ' . Article::class . '.',
        ];

        yield 'untyped without cast' => [
            static fn (): ScopeConfig => new ScopeConfig('near'),
            'Scope "near" argument "lat" ' . $untyped,
        ];

        yield 'unknown default argument' => [
            static fn (): ScopeConfig => new ScopeConfig('publishedBetween')
                ->default(foo: 1),
            'Scope "publishedBetween" has no argument "foo".',
        ];

        yield 'unknown cast argument' => [
            static fn (): ScopeConfig => new ScopeConfig('publishedBetween')
                ->cast(foo: Type::String),
            'Scope "publishedBetween" has no argument "foo".',
        ];

        yield 'positional rules' => [
            static fn (): ScopeConfig => new ScopeConfig('ofStatuses')->rules([
                'max:2',
            ]),
            'Scope "ofStatuses" options must use named arguments.',
        ];

        yield 'union type' => [
            static fn (): ScopeConfig => new ScopeConfig('union'),
            'Scope "union" argument "value" ' . $untyped,
        ];

        yield 'unknown context argument' => [
            static fn (): ScopeConfig => new ScopeConfig('ownedBy')
                ->context('foo'),
            'Scope "ownedBy" has no argument "foo".',
        ];

        yield 'rules on a context argument' => [
            static fn (): ScopeConfig => new ScopeConfig('ownedBy')
                ->context('author')
                ->rules(author: ['required']),
            'Scope "ownedBy" argument "author" ' . $context . ' rules().',
        ];

        yield 'cast on a context argument' => [
            static fn (): ScopeConfig => new ScopeConfig('ownedBy')
                ->context('author')
                ->cast(author: Type::Integer),
            'Scope "ownedBy" argument "author" ' . $context . ' cast().',
        ];

        yield 'each on a context argument' => [
            static fn (): ScopeConfig => new ScopeConfig('among')
                ->context('ids')
                ->each(ids: ['integer']),
            'Scope "among" argument "ids" ' . $context . ' each().',
        ];

        yield 'messages on a context argument' => [
            static fn (): ScopeConfig => new ScopeConfig('ownedBy')
                ->context('author')
                ->messages(author: ['required' => 'Foo.']),
            'Scope "ownedBy" argument "author" ' . $context . ' messages().',
        ];

        yield 'attribute on a context argument' => [
            static fn (): ScopeConfig => new ScopeConfig('ownedBy')
                ->context('author')
                ->attribute(author: 'auteur'),
            'Scope "ownedBy" argument "author" ' . $context . ' attribute().',
        ];

        yield 'unknown nested argument' => [
            static fn (): ScopeConfig => new ScopeConfig('inRanges')->nested(
                foo: ['*' => ['array']],
            ),
            'Scope "inRanges" has no argument "foo".',
        ];

        yield 'positional nested' => [
            static fn (): ScopeConfig => new ScopeConfig('inRanges')->nested([
                '*' => ['array'],
            ]),
            'Scope "inRanges" options must use named arguments.',
        ];

        yield 'nested on a context argument' => [
            static fn (): ScopeConfig => new ScopeConfig('among')
                ->context('ids')
                ->nested(ids: ['*' => ['integer']]),
            'Scope "among" argument "ids" ' . $context . ' nested().',
        ];

        yield 'unknown nested attribute argument' => [
            static fn (): ScopeConfig => new ScopeConfig('inRanges')->attribute(
                foo: ['*.min' => 'minimum'],
            ),
            'Scope "inRanges" has no argument "foo".',
        ];

        yield 'nested attribute on a context argument' => [
            static fn (): ScopeConfig => new ScopeConfig('among')
                ->context('ids')
                ->attribute(ids: ['*' => 'identifiant']),
            'Scope "among" argument "ids" ' . $context . ' attribute().',
        ];

        yield 'cast to non enum class' => [
            static fn (): ScopeConfig => new ScopeConfig('near')->cast(
                // @phpstan-ignore argument.type
                lat: stdClass::class,
                lng: Type::Float,
            ),
            'Scope "near" argument "lat" cast must be a Type, a backed enum'
                . ' or a closure.',
        ];
    }

    /**
     * @return list<ScopeArgument>
     */
    private function arguments(
        ScopeConfig $config,
        Model $model = new Article(),
    ): array {
        return new ScopeReflector()->reflect($model, $config)[0];
    }
}
