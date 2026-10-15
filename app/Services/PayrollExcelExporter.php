<?php

namespace App\Services;

use App\Models\Salary;
use RuntimeException;
use ZipArchive;

class PayrollExcelExporter
{
    /**
     * @param  iterable<Salary>  $salaries
     */
    public function export(iterable $salaries, string $period, ?string $branchName): string
    {
        $path = tempnam(sys_get_temp_dir(), 'payroll-xlsx-');
        if ($path === false) {
            throw new RuntimeException('Unable to allocate a temporary file for the payroll workbook.');
        }

        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            @unlink($path);
            throw new RuntimeException('Unable to create the payroll Excel workbook.');
        }

        try {
            $zip->addFromString('[Content_Types].xml', $this->contentTypesXml());
            $zip->addFromString('_rels/.rels', $this->rootRelationshipsXml());
            $zip->addFromString('xl/workbook.xml', $this->workbookXml());
            $zip->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationshipsXml());
            $zip->addFromString('xl/styles.xml', $this->stylesXml());
            $zip->addFromString('xl/worksheets/sheet1.xml', $this->worksheetXml($salaries, $period, $branchName));

            if (! $zip->close()) {
                throw new RuntimeException('Unable to finish writing the payroll Excel workbook.');
            }
        } catch (\Throwable $exception) {
            $zip->close();
            @unlink($path);

            throw $exception;
        }

        return $path;
    }

    /**
     * @param  iterable<Salary>  $salaries
     */
    private function worksheetXml(iterable $salaries, string $period, ?string $branchName): string
    {
        $headers = [
            'Cabang', 'Periode', 'NIP', 'Nama Pegawai', 'Jenis',
            'Gaji Pokok', 'Jam Mengajar', 'Tarif per Jam', 'Tambahan Gaji Jabatan', 'Rincian Jabatan',
            'Total Insentif', 'Rincian Insentif', 'Total Tunjangan',
            'Rincian Tunjangan', 'Potongan', 'Gaji Bersih', 'Status',
        ];
        $rows = [];
        $rows[] = $this->rowXml(1, $headers, true);
        $rows[] = $this->rowXml(2, [
            'Periode laporan', $period, 'Cabang', $branchName ?? 'Semua cabang',
        ]);

        $rowNumber = 4;
        foreach ($salaries as $salary) {
            $employee = $salary->employee;
            $positionBreakdown = collect($salary->position_details ?? [])
                ->map(function (array $detail): string {
                    $name = $detail['name'] ?? 'Jabatan';
                    if (! empty($detail['class_name'])) {
                        $name .= ' · '.$detail['class_name'];
                    }

                    return $name
                        .' (gaji +'.number_format((float) ($detail['salary_addition'] ?? 0), 2, '.', '')
                        .', tunjangan '.number_format((float) ($detail['allowance'] ?? 0), 2, '.', '')
                        .', insentif '.number_format((float) ($detail['incentive'] ?? 0), 2, '.', '').')';
                })
                ->implode('; ');
            $incentiveBreakdown = collect($salary->incentive_details ?? [])
                ->map(fn (array $detail): string => ($detail['name'] ?? 'Insentif').' = '.number_format((float) ($detail['amount'] ?? 0), 2, '.', ''))
                ->implode('; ');
            $allowanceBreakdown = collect($salary->allowance_details ?? [])
                ->map(function (array $detail): string {
                    $name = $detail['name'] ?? 'Tunjangan';
                    if (isset($detail['count'], $detail['rate'])) {
                        $name .= ' ('.(int) $detail['count'].' jam x '.number_format((float) $detail['rate'], 2, '.', '').')';
                    }

                    return $name.' = '.number_format((float) ($detail['amount'] ?? 0), 2, '.', '');
                })
                ->implode('; ');

            $rows[] = $this->rowXml($rowNumber++, [
                $employee?->branch?->name ?? '',
                $salary->period,
                $employee?->nip ?? '',
                $employee?->name ?? 'Pegawai terhapus',
                $employee?->type === 'GURU' ? 'Guru' : ($employee?->type === 'STAFF' ? 'Staff' : ''),
                (float) $salary->base_salary,
                (int) $salary->teaching_hours,
                (float) $salary->hourly_rate,
                (float) $salary->position_salary,
                $positionBreakdown,
                (float) $salary->incentives,
                $incentiveBreakdown,
                (float) $salary->allowances,
                $allowanceBreakdown,
                (float) $salary->deductions,
                (float) $salary->net_salary,
                $salary->status === 'PAID' ? 'Dibayar' : 'Pending',
            ], false, [6, 8, 9, 11, 13, 15, 16]);
        }

        $lastRow = max(4, $rowNumber - 1);
        $columns = [
            ['min' => 1, 'max' => 1, 'width' => 24],
            ['min' => 2, 'max' => 2, 'width' => 13],
            ['min' => 3, 'max' => 3, 'width' => 20],
            ['min' => 4, 'max' => 4, 'width' => 28],
            ['min' => 5, 'max' => 5, 'width' => 12],
            ['min' => 6, 'max' => 6, 'width' => 18],
            ['min' => 7, 'max' => 7, 'width' => 14],
            ['min' => 8, 'max' => 8, 'width' => 18],
            ['min' => 9, 'max' => 9, 'width' => 18],
            ['min' => 10, 'max' => 10, 'width' => 60],
            ['min' => 11, 'max' => 11, 'width' => 17],
            ['min' => 12, 'max' => 12, 'width' => 45],
            ['min' => 13, 'max' => 13, 'width' => 17],
            ['min' => 14, 'max' => 14, 'width' => 55],
            ['min' => 15, 'max' => 16, 'width' => 17],
            ['min' => 17, 'max' => 17, 'width' => 14],
        ];
        $columnXml = implode('', array_map(
            fn (array $column): string => '<col min="'.$column['min'].'" max="'.$column['max'].'" width="'.$column['width'].'" customWidth="1"/>',
            $columns
        ));

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="3" topLeftCell="A4" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .'<cols>'.$columnXml.'</cols><sheetData>'.implode('', $rows).'</sheetData>'
            .'<autoFilter ref="A1:Q'.$lastRow.'"/>'
            .'</worksheet>';
    }

    /**
     * @param  array<int, string|int|float>  $values
     * @param  array<int, int>  $currencyColumns
     */
    private function rowXml(int $rowNumber, array $values, bool $header = false, array $currencyColumns = []): string
    {
        $cells = '';
        foreach ($values as $index => $value) {
            $column = $this->columnName($index + 1);
            $reference = $column.$rowNumber;
            if (is_int($value) || is_float($value)) {
                $style = in_array($index + 1, $currencyColumns, true) ? ' s="2"' : '';
                $cells .= '<c r="'.$reference.'"'.$style.'><v>'.(string) $value.'</v></c>';

                continue;
            }

            $style = $header ? ' s="1"' : '';
            $cells .= '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$this->xmlEscape((string) $value).'</t></is></c>';
        }

        return '<row r="'.$rowNumber.'">'.$cells.'</row>';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $remainder = ($number - 1) % 26;
            $name = chr(65 + $remainder).$name;
            $number = intdiv($number - 1, 26);
        }

        return $name;
    }

    private function xmlEscape(string $value): string
    {
        $value = preg_replace('/[^\x09\x0A\x0D\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';

        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    private function contentTypesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>';
    }

    private function rootRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>';
    }

    private function workbookXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="Payroll" sheetId="1" r:id="rId1"/></sheets>'
            .'</workbook>';
    }

    private function workbookRelationshipsXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<numFmts count="1"><numFmt numFmtId="164" formatCode="#,##0.00"/></numFmts>'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><color rgb="FFFFFFFF"/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill><fill><patternFill patternType="solid"><fgColor rgb="FF312E81"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left style="thin"><color rgb="FFE2E8F0"/></left><right style="thin"><color rgb="FFE2E8F0"/></right><top style="thin"><color rgb="FFE2E8F0"/></top><bottom style="thin"><color rgb="FFE2E8F0"/></bottom><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="3"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf><xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>';
    }
}
