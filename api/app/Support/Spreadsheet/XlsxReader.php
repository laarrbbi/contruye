<?php

namespace App\Support\Spreadsheet;

use Generator;
use RuntimeException;
use XMLReader;
use ZipArchive;

/**
 * Reads the rows of an .xlsx one by one, from its XML, without its styles:
 * a 25,000-row company export fits in a few megabytes of memory, and a file
 * whose style sheet a library rejects still reads.
 */
class XlsxReader
{
    private array $shared = [];

    public function __construct(private readonly string $path)
    {
        if (! is_file($path)) {
            throw new RuntimeException("No such file: {$path}");
        }
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Not an .xlsx file: {$path}");
        }
        $strings = $zip->getFromName('xl/sharedStrings.xml');
        $zip->close();
        if ($strings !== false) {
            $reader = new XMLReader;
            $reader->XML($strings);
            while ($reader->read()) {
                if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'si') {
                    $node = simplexml_load_string($reader->readOuterXml());
                    $text = '';
                    foreach ($node->xpath('.//*[local-name()="t"]') as $t) {
                        $text .= (string) $t;
                    }
                    $this->shared[] = $text;
                }
            }
        }
    }

    /** How many worksheets the file has (sheet1.xml, sheet2.xml…). */
    public function sheetCount(): int
    {
        $zip = new ZipArchive;
        $zip->open($this->path);
        $count = 0;
        while ($zip->locateName('xl/worksheets/sheet'.($count + 1).'.xml') !== false) {
            $count++;
        }
        $zip->close();

        return $count;
    }

    /** @return Generator<int, list<?string>> each row, as a list indexed by column */
    public function rows(int $sheet = 1): Generator
    {
        $reader = new XMLReader;
        if (! $reader->open('zip://'.$this->path.'#xl/worksheets/sheet'.$sheet.'.xml')) {
            throw new RuntimeException("No sheet {$sheet} in {$this->path}");
        }
        while ($reader->read()) {
            if ($reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $row = simplexml_load_string($reader->readOuterXml());
            $cells = [];
            foreach ($row->children() as $cell) {
                $ref = (string) $cell['r'];
                $type = (string) $cell['t'];
                $value = null;
                if ($type === 's') {
                    $value = $this->shared[(int) $cell->v] ?? null;
                } elseif ($type === 'inlineStr') {
                    $value = '';
                    foreach ($cell->xpath('.//*[local-name()="t"]') as $t) {
                        $value .= (string) $t;
                    }
                } elseif (isset($cell->v)) {
                    $value = (string) $cell->v;
                }
                $cells[$this->column($ref)] = $value;
            }
            if ($cells === []) {
                yield [];

                continue;
            }
            $list = array_fill(0, max(array_keys($cells)) + 1, null);
            foreach ($cells as $i => $v) {
                $list[$i] = $v;
            }
            yield $list;
        }
        $reader->close();
    }

    private function column(string $ref): int
    {
        $n = 0;
        foreach (str_split(preg_replace('/\d/', '', $ref)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }
}
