<?php

namespace App\Services;

use App\Models\CatalogImport;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\Submission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class WorkbookCatalogImporter
{
    public function import(string $path, int $year, bool $replace = false): CatalogImport
    {
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
            throw new RuntimeException('Workbook .xlsx tidak ditemukan.');
        }
        if (filesize($path) > 25 * 1024 * 1024) {
            throw new RuntimeException('Ukuran workbook melebihi batas 25 MB.');
        }

        $reportingYear = ReportingYear::firstOrCreate(['year' => $year], ['status' => 'open']);
        if ($replace && Submission::where('reporting_year_id', $reportingYear->id)->exists()) {
            throw new RuntimeException('Katalog tidak dapat diganti karena Tahun Pelaporan sudah memiliki Nilai Indikator.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row <= 15;
            }
        });
        $spreadsheet = $reader->load($path);
        $worksheetNames = $spreadsheet->getSheetNames();
        $sourceSheets = array_values(array_filter($worksheetNames, fn (string $name) => Str::lower(trim($name)) !== 'resume'));
        $tables = [];

        foreach ($sourceSheets as $position => $sheetName) {
            $tables[] = $this->readTable($spreadsheet->getSheetByName($sheetName), $sheetName, $position + 1);
        }
        $spreadsheet->disconnectWorksheets();

        $report = [
            'workbook_sheets' => count($worksheetNames),
            'resume_sheets' => count($worksheetNames) - count($sourceSheets),
            'reporting_tables' => count($tables),
            'mapping_ready' => 0,
            'mapping_pending' => count($tables),
            'tables' => $tables,
            'warnings' => [
                'Resume tidak diimpor sebagai Tabel Pelaporan karena merupakan rekap otomatis.',
                'Indikator belum dibuat otomatis; struktur header workbook harus dipetakan dan divalidasi per tabel.',
                'Rumus dan hasil rumus workbook tidak diimpor sebagai sumber nilai resmi.',
            ],
        ];

        return DB::transaction(function () use ($reportingYear, $path, $replace, $tables, $report) {
            if ($replace) {
                ReportingTable::where('reporting_year_id', $reportingYear->id)->delete();
            }

            $ready = 0;
            foreach ($tables as $index => $table) {
                $reportingTable = ReportingTable::firstOrNew([
                    'reporting_year_id' => $reportingYear->id,
                    'code' => $table['code'],
                ]);
                $mappingStatus = ! $replace && $reportingTable->exists && $reportingTable->mapping_status === 'ready' && in_array($table['code'], ['T02', 'T03', 'T04', 'T05', 'T06'], true)
                    ? 'ready'
                    : 'pending';
                $ready += $mappingStatus === 'ready' ? 1 : 0;
                $report['tables'][$index]['mapping_status'] = $mappingStatus;
                $reportingTable->fill([
                    'name' => $table['name'],
                    'description' => $table['description'],
                    'source_sheet' => $table['source_sheet'],
                    'mapping_status' => $mappingStatus,
                    'source_metadata' => ['header_candidates' => $table['header_candidates']],
                    'position' => $table['position'],
                ])->save();
            }
            $report['mapping_ready'] = $ready;
            $report['mapping_pending'] = count($tables) - $ready;

            return CatalogImport::create([
                'reporting_year_id' => $reportingYear->id,
                'filename' => basename($path),
                'checksum' => hash_file('sha256', $path),
                'report' => $report,
                'imported_at' => now(),
            ]);
        });
    }

    private function readTable(?Worksheet $sheet, string $sheetName, int $position): array
    {
        if (! $sheet) {
            throw new RuntimeException("Lembar {$sheetName} tidak dapat dibaca.");
        }

        $titleLines = [];
        for ($row = 2; $row <= 5; $row++) {
            $line = $this->rowText($sheet, $row);
            if ($line !== '' && ! $this->isMetadataLine($line)) {
                $titleLines[] = $line;
            }
        }
        $title = $titleLines ? implode(' ', array_unique($titleLines)) : "Tabel {$sheetName}";

        $headers = [];
        for ($row = 7; $row <= 10; $row++) {
            foreach ($this->rowValues($sheet, $row) as $value) {
                if ($this->isHeaderCandidate($value)) {
                    $headers[] = $value;
                }
            }
        }

        return [
            'code' => $this->tableCode($sheetName),
            'name' => Str::limit($title, 255, ''),
            'description' => "Bersumber dari lembar {$sheetName} workbook Profil Kesehatan.",
            'source_sheet' => $sheetName,
            'position' => $position,
            'header_candidates' => array_values(array_unique($headers)),
        ];
    }

    private function rowText(Worksheet $sheet, int $row): string
    {
        return implode(' ', $this->rowValues($sheet, $row));
    }

    private function rowValues(Worksheet $sheet, int $row): array
    {
        $values = [];
        for ($column = 1; $column <= 100; $column++) {
            $value = preg_replace('/\s+/u', ' ', trim((string) $sheet->getCell([$column, $row])->getValue()));
            if ($value !== '' && ! str_starts_with($value, '=')) {
                $values[] = $value;
            }
        }

        return $values;
    }

    private function isMetadataLine(string $line): bool
    {
        $normalized = Str::upper($line);

        return preg_match('/^TABEL\s+/u', $normalized)
            || in_array($normalized, ['PROVINSI', 'TAHUN'], true);
    }

    private function isHeaderCandidate(string $value): bool
    {
        $normalized = Str::upper($value);

        return preg_match('/[A-Z]/u', $normalized)
            && ! preg_match('/^[\d().+\-\s]+$/u', $normalized)
            && ! in_array($normalized, ['NO', 'NOMOR', 'PROVINSI', 'TAHUN'], true);
    }

    private function tableCode(string $sheetName): string
    {
        if (preg_match('/^(\d+)\s*([a-z])?$/i', trim($sheetName), $matches)) {
            return 'T'.str_pad($matches[1], 2, '0', STR_PAD_LEFT).strtoupper($matches[2] ?? '');
        }

        return 'T'.strtoupper(preg_replace('/[^0-9A-Z]+/i', '', $sheetName));
    }
}
