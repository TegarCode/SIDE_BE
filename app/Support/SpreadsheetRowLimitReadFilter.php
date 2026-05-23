<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class SpreadsheetRowLimitReadFilter implements IReadFilter
{
    public function __construct(
        private readonly int $maxRow
    ) {
    }

    public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
    {
        return $row <= $this->maxRow;
    }
}
