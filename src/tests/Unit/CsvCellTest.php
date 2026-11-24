<?php

namespace Tests\Unit;

use App\Support\CsvCell;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CsvCellTest extends TestCase
{
    public static function cells(): array
    {
        return [
            ['=1+1', "'=1+1"], [' +1', "' +1"], ['-1', "'-1"], ['@SUM(A1)', "'@SUM(A1)"],
            ["\tplain", "'\tplain"], ["\rtest", "'\rtest"], ["\ntext", "'\ntext"],
            ['Маникюр', 'Маникюр'], ['₽', '₽'], ['Алиса; Боб', 'Алиса; Боб'], ['', ''],
        ];
    }

    #[DataProvider('cells')]
    public function test_untrusted_cells(string $input, string $expected): void
    {
        $this->assertSame($expected, CsvCell::text($input));
    }
}
