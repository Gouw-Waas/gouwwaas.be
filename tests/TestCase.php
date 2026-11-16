<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use OpenSpout\Reader\XLSX\Reader;

abstract class TestCase extends BaseTestCase
{
    /**
     * The rows of the first sheet of an .xlsx file, as arrays of cell values.
     *
     * @return array<int, array<int, mixed>>
     */
    protected function readXlsx(string $content): array
    {
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, $content);

        try {
            $reader = new Reader;
            $reader->open($file);
            $rows = [];

            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $rows[] = $row->toArray();
                }
                break;
            }

            $reader->close();

            return $rows;
        } finally {
            @unlink($file);
        }
    }
}
