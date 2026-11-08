<?php

namespace App\Support\Financials;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;
use ZipArchive;

/**
 * Writes values into a copy of one of the package's Excel files, and nothing
 * else: the formulas, styles, lists and the iterative calculation travel
 * untouched. Used for a user's download: their inputs and the current legal
 * parameters, written over a copy of the corrected .xlsx
 * (ESPECIFICACION_TECNICA.md, section 10).
 *
 * The package's files carry no cached results and ask for a full calculation
 * on opening (calcPr fullCalcOnLoad), so Excel or LibreOffice calculates them
 * with the values written here. A number goes in as a number, a text as an
 * inline string; the cell keeps its style. An input whose default is a
 * formula loses that formula only when the user wrote over it.
 */
final class XlsxFiller
{
    private const MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @param  array<string, array<string, int|float|string>>  $values  sheet name => [cell => value]
     * @return string the path of the filled copy (a temporary file the caller sends and deletes)
     */
    public static function fill(string $source, array $values): string
    {
        if (! is_file($source)) {
            throw new RuntimeException("No such workbook: {$source}");
        }

        $copy = tempnam(sys_get_temp_dir(), 'xlsx');
        copy($source, $copy);

        $zip = new ZipArchive;
        if ($zip->open($copy) !== true) {
            throw new RuntimeException("Not a workbook: {$source}");
        }

        try {
            $paths = self::sheetPaths($zip);

            foreach ($values as $sheet => $cells) {
                if (! isset($paths[$sheet])) {
                    throw new RuntimeException("The workbook has no sheet «{$sheet}»");
                }
                $xml = self::document((string) $zip->getFromName($paths[$sheet]));
                foreach ($cells as $address => $value) {
                    self::write($xml, $address, $value);
                }
                $zip->addFromString($paths[$sheet], $xml->saveXML());
            }
        } finally {
            $zip->close();
        }

        return $copy;
    }

    /**
     * Sheet name => its part in the archive, from the workbook and its relationships.
     *
     * @return array<string, string>
     */
    private static function sheetPaths(ZipArchive $zip): array
    {
        $rels = self::document((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $targets = [];
        foreach ($rels->getElementsByTagName('Relationship') as $rel) {
            $target = ltrim($rel->getAttribute('Target'), '/');
            $targets[$rel->getAttribute('Id')] = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        $paths = [];
        foreach (self::document((string) $zip->getFromName('xl/workbook.xml'))->getElementsByTagNameNS(self::MAIN, 'sheet') as $sheet) {
            $paths[$sheet->getAttribute('name')] = $targets[$sheet->getAttributeNS(self::REL, 'id')] ?? '';
        }

        return $paths;
    }

    private static function document(string $xml): DOMDocument
    {
        $document = new DOMDocument;
        if ($xml === '' || ! $document->loadXML($xml, LIBXML_NONET)) {
            throw new RuntimeException('Unreadable part of the workbook');
        }

        return $document;
    }

    private static function write(DOMDocument $xml, string $address, int|float|string $value): void
    {
        if (preg_match('/^([A-Z]{1,3})([1-9]\d{0,6})$/', $address, $parts) !== 1) {
            throw new RuntimeException("Not a cell address: {$address}");
        }

        $cell = self::cell($xml, $parts[1], (int) $parts[2]);

        // Whatever the cell held (a value, a formula, a string) goes; its style stays.
        while ($cell->firstChild !== null) {
            $cell->removeChild($cell->firstChild);
        }

        if (is_string($value)) {
            $cell->setAttribute('t', 'inlineStr');
            $is = $xml->createElementNS(self::MAIN, 'is');
            $text = $xml->createElementNS(self::MAIN, 't');
            $text->appendChild($xml->createTextNode($value));
            if (trim($value) !== $value) {
                $text->setAttribute('xml:space', 'preserve');
            }
            $is->appendChild($text);
            $cell->appendChild($is);

            return;
        }

        $cell->setAttribute('t', 'n');
        $cell->appendChild($xml->createElementNS(self::MAIN, 'v', self::number($value)));
    }

    /** The cell element at an address, created in its row and in column order when absent. */
    private static function cell(DOMDocument $xml, string $column, int $row): DOMElement
    {
        $xpath = new DOMXPath($xml);
        $xpath->registerNamespace('m', self::MAIN);
        $address = $column.$row;

        $found = $xpath->query("//m:sheetData/m:row[@r='{$row}']/m:c[@r='{$address}']")->item(0);
        if ($found instanceof DOMElement) {
            return $found;
        }

        $sheetData = $xpath->query('//m:sheetData')->item(0);
        $rowElement = $xpath->query("//m:sheetData/m:row[@r='{$row}']")->item(0);
        if (! $rowElement instanceof DOMElement) {
            $rowElement = $xml->createElementNS(self::MAIN, 'row');
            $rowElement->setAttribute('r', (string) $row);
            $before = null;
            foreach ($xpath->query('m:row', $sheetData) as $candidate) {
                if ((int) $candidate->getAttribute('r') > $row) {
                    $before = $candidate;
                    break;
                }
            }
            $sheetData->insertBefore($rowElement, $before);
        }

        $cell = $xml->createElementNS(self::MAIN, 'c');
        $cell->setAttribute('r', $address);
        $before = null;
        foreach ($xpath->query('m:c', $rowElement) as $candidate) {
            if (self::columnIndex(preg_replace('/\d+$/', '', $candidate->getAttribute('r'))) > self::columnIndex($column)) {
                $before = $candidate;
                break;
            }
        }
        $rowElement->insertBefore($cell, $before);

        return $cell;
    }

    private static function columnIndex(string $column): int
    {
        $index = 0;
        foreach (str_split($column) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index;
    }

    private static function number(int|float $value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        // Enough digits to read back the same double, no exponent for Excel's sake.
        $text = rtrim(rtrim(sprintf('%.15F', $value), '0'), '.');

        return $text === '-0' ? '0' : $text;
    }
}
