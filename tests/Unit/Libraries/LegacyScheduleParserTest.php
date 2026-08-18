<?php

declare(strict_types=1);

namespace Tests\Unit\Libraries;

use App\Libraries\LegacyMigration\LegacyScheduleParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class LegacyScheduleParserTest extends TestCase
{
    private LegacyScheduleParser $parser;

    protected function setUp(): void
    {
        $this->parser = new LegacyScheduleParser();
    }

    #[DataProvider('validTimes')]
    public function testNormalizesLegacyTimeSuffixes(string $raw, string $expected): void
    {
        $this->assertSame($expected, $this->parser->parse('2017-03-25', $raw));
    }

    /** @return iterable<string, array{string, string}> */
    public static function validTimes(): iterable
    {
        yield 'plain time' => ['16:30', '2017-03-25 16:30:00'];
        yield 'lowercase suffix' => ['16:30 hrs', '2017-03-25 16:30:00'];
        yield 'mixed case suffix' => ['17:00 Hrs', '2017-03-25 17:00:00'];
        yield 'hour suffix' => ['9:00 horas', '2017-03-25 09:00:00'];
        yield 'seconds' => ['21:00:00', '2017-03-25 21:00:00'];
        yield 'hour only' => ['20 hrs', '2017-03-25 20:00:00'];
        yield 'dot separator' => ['21.00 hrs', '2017-03-25 21:00:00'];
        yield 'legacy trailing zero typo' => ['21:000 hrs', '2017-03-25 21:00:00'];
    }

    #[DataProvider('invalidTimes')]
    public function testRejectsInvalidTimesInsteadOfUsingMidnight(string $raw): void
    {
        $this->assertNull($this->parser->parse('2017-03-25', $raw));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidTimes(): iterable
    {
        yield 'empty' => [''];
        yield 'invalid text' => ['por confirmar'];
        yield 'invalid hour' => ['25:00 hrs'];
        yield 'invalid minutes' => ['16:75 hrs'];
    }

    public function testRejectsInvalidDates(): void
    {
        $this->assertNull($this->parser->parse('2017-02-31', '16:30 hrs'));
    }

    public function testPreservesMultipleExplicitFunctionsInOneLegacyField(): void
    {
        $this->assertSame([
            '2017-03-25 12:00:00',
            '2017-03-25 16:30:00',
        ], $this->parser->parseMany('2017-03-25', '12:00 y 16:30 hrs'));
    }
}
