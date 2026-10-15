<?php

namespace App\Services;

use RuntimeException;
use ZipArchive;

class TabularExcelExporter
{
    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    public function export(array $headers, iterable $rows, string $sheetName): string
    {
        $path = tempnam(sys_get_temp_dir(), 'report-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary file for the report workbook.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);

            throw new RuntimeException('Unable to create the report Excel workbook.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
                .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
                .'<Default Extension="xml" ContentType="application/xml"/>'
                .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
                .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
                .'</Types>');
            $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
                .'</Relationships>');
            $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
                .'<sheets><sheet name="'.$this->escape($sheetName).'" sheetId="1" r:id="rId1"/></sheets></workbook>');
            $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
                .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
                .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
                .'</Relationships>');
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($headers, $rows));
            if (! $zip->close()) {
                throw new RuntimeException('Unable to finish writing the report Excel workbook.');
            }
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($path);

            throw $exception;
        }

        return $path;
    }

    /**
     * @param  array<int, string>  $headers
     * @param  iterable<array<int, mixed>>  $rows
     */
    private function worksheetXml(array $headers, iterable $rows): string
    {
        $xmlRows = [$this->rowXml(1, $headers)];
        $index = 2;
        foreach ($rows as $row) {
            $xmlRows[] = $this->rowXml($index++, $row);
        }

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<sheetData>'.implode('', $xmlRows).'</sheetData></worksheet>';
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function rowXml(int $rowNumber, array $values): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $reference = $this->columnName($index + 1).$rowNumber;
            if (is_int($value) || is_float($value)) {
                $cells .= '<c r="'.$reference.'"><v>'.$value.'</v></c>';

                continue;
            }

            $cells .= '<c r="'.$reference.'" t="inlineStr"><is><t xml:space="preserve">'.$this->escape((string) ($value ?? '')).'</t></is></c>';
        }

        return '<row r="'.$rowNumber.'">'.$cells.'</row>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $name = chr(65 + (($number - 1) % 26)).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function escape(string $value): string
    {
        $value = preg_replace('/[^\x09\x0A\x0D\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
