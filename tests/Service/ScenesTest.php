<?php declare(strict_types=1);

namespace ScaleCommerce\VideoOptimizer\Tests\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ScaleCommerce\VideoOptimizer\Service\Scenes;

class ScenesTest extends TestCase
{
    /**
     * @return iterable<string, array{int, list<int>}>
     */
    public static function indexProvider(): iterable
    {
        yield 'three' => [3, [0, 5, 9]];
        yield 'five' => [5, [0, 2, 5, 7, 9]];
        yield 'all ten' => [10, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]];
        yield 'too many' => [50, [0, 1, 2, 3, 4, 5, 6, 7, 8, 9]];
    }

    /**
     * @param list<int> $expected
     */
    #[DataProvider('indexProvider')]
    public function testIndicesAreSpreadEvenly(int $count, array $expected): void
    {
        static::assertSame($expected, Scenes::indices($count));
    }

    public function testStartMirrorsTheEncoderSampling(): void
    {
        // The encoder samples duration / 11 * (i + 1).
        static::assertSame(5, Scenes::start(65, 0));
        static::assertSame(59, Scenes::start(65, 9));
        static::assertSame(0, Scenes::start(5, 0));
        // The float duration is not rounded: 65.9 / 11 * 10 = 59.9.
        static::assertSame(59, Scenes::start(65.9, 9));
        static::assertSame(17, Scenes::start(65.4, 2));
    }

    public function testTimeFormat(): void
    {
        static::assertSame('0:05', Scenes::formatTime(5));
        static::assertSame('1:05', Scenes::formatTime(65));
        static::assertSame('1:01:05', Scenes::formatTime(3665));
        static::assertSame('0:00', Scenes::formatTime(-3));
    }

    public function testTitlesDropTagsTrailingEmptyLinesAndOverlongOnes(): void
    {
        static::assertSame(['A', '', 'C'], Scenes::titles("<i>A</i>\n\n C \n\n"));
        static::assertSame([], Scenes::titles(null));
        static::assertCount(10, Scenes::titles(implode("\n", range(1, 15))));
        static::assertSame(80, mb_strlen(Scenes::titles(str_repeat('x', 200))[0]));
    }

    public function testBuildWithoutDurationIsEmpty(): void
    {
        static::assertSame([], Scenes::build(0, 5, [], static fn (int $i): string => "t$i"));
    }
}
