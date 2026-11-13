<?php

namespace App\Support\Financials;

use DOMDocument;
use DOMXPath;
use ZipArchive;

/**
 * The number format of each cell of one sheet of a package workbook, as
 * Excel shows it («#,##0.00 "€"», «0.00%»), read from the file's styles.
 *
 * For a template whose JSON carries no format for its results (the
 * professional's «números gordos»: its formulas come without one), the
 * calculator shows each result as the delivered .xlsx formats it. Read only;
 * cells in the General format are left out.
 */
final class XlsxFormats
{
    private const MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /** Excel's built-in formats a package file may use (ECMA-376, 18.8.30). */
    private const BUILT_IN = [
        1 => '0', 2 => '0.00', 3 => '#,##0', 4 => '#,##0.00', 9 => '0%', 10 => '0.00%', 11 => '0.00E+00', 14 => 'dd/mm/yyyy',
    ];

    /** @return array<string, string> cell address => number format */
    public static function of(string $path, string $sheet): array
    {
        $zip = new ZipArchive;
        if (! is_file($path) || $zip->open($path) !== true) {
            return [];
        }

        try {
            $part = self::sheetPart($zip, $sheet);
            $styles = (string) $zip->getFromName('xl/styles.xml');
            $xml = $part === null ? '' : (string) $zip->getFromName($part);
        } finally {
            $zip->close();
        }

        if ($xml === '' || $styles === '') {
            return [];
        }

        $styleXPath = self::xpath($styles);
        $codes = self::BUILT_IN;
        foreach ($styleXPath->query('//m:numFmts/m:numFmt') as $format) {
            $codes[(int) $format->getAttribute('numFmtId')] = $format->getAttribute('formatCode');
        }
        $byStyle = [];
        foreach ($styleXPath->query('//m:cellXfs/m:xf') as $index => $xf) {
            $byStyle[$index] = $codes[(int) $xf->getAttribute('numFmtId')] ?? null;
        }

        $formats = [];
        foreach (self::xpath($xml)->query('//m:sheetData/m:row/m:c[@s]') as $cell) {
            $format = $byStyle[(int) $cell->getAttribute('s')] ?? null;
            if ($format !== null && $format !== '' && $format !== 'General') {
                $formats[$cell->getAttribute('r')] = $format;
            }
        }

        return $formats;
    }

    private static function sheetPart(ZipArchive $zip, string $sheet): ?string
    {
        $workbook = self::xpath((string) $zip->getFromName('xl/workbook.xml'));
        $rels = self::xpath((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
        $rels->registerNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');

        foreach ($workbook->query('//m:sheets/m:sheet') as $entry) {
            if ($entry->getAttribute('name') !== $sheet) {
                continue;
            }
            $id = $entry->getAttributeNS(self::REL, 'id');
            foreach ($rels->query('//r:Relationship') as $rel) {
                if ($rel->getAttribute('Id') === $id) {
                    $target = ltrim($rel->getAttribute('Target'), '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }

        return null;
    }

    private static function xpath(string $xml): DOMXPath
    {
        $document = new DOMDocument;
        $document->loadXML($xml, LIBXML_NONET);
        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('m', self::MAIN);

        return $xpath;
    }
}
