<?php

namespace App\Support;

final class CsvCell
{
    public static function text(string $value): string
    {
        // Spreadsheet applications can interpret quoted fields as formulas.
        return preg_match('/^(?:[\x00-\x20]*[=+@-]|[\t\r\n])/u', $value) === 1
            ? "'" . $value : $value;
    }
}
