<?php

namespace Tests\Feature;

use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Services\WorkbookCatalogImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class WorkbookCatalogImporterTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_numbered_sheets_and_skips_resume(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'profile-').'.xlsx';
        $workbook = new Spreadsheet;
        $resume = $workbook->getActiveSheet();
        $resume->setTitle('Resume');
        $sheet = $workbook->createSheet();
        $sheet->setTitle('1');
        $sheet->setCellValue('A1', 'TABEL 1');
        $sheet->setCellValue('A3', 'LUAS WILAYAH DAN JUMLAH PENDUDUK');
        $sheet->setCellValue('A7', 'NO');
        $sheet->setCellValue('B7', 'KABUPATEN/KOTA');
        $sheet->setCellValue('C7', 'JUMLAH PENDUDUK');
        (new Xlsx($workbook))->save($path);

        $import = app(WorkbookCatalogImporter::class)->import($path, 2024);

        $this->assertSame(2, $import->report['workbook_sheets']);
        $this->assertSame(1, $import->report['reporting_tables']);
        $this->assertSame(1, $import->report['mapping_pending']);
        $this->assertDatabaseHas('reporting_tables', [
            'code' => 'T01',
            'name' => 'LUAS WILAYAH DAN JUMLAH PENDUDUK',
            'source_sheet' => '1',
            'mapping_status' => 'pending',
        ]);

        unlink($path);
    }

    public function test_replace_is_blocked_when_reporting_data_exists(): void
    {
        $this->seed();
        $year = ReportingYear::where('year', 2024)->firstOrFail();
        $table = ReportingTable::where('reporting_year_id', $year->id)->firstOrFail();
        $table->submissions()->create([
            'region_id' => 1,
            'reporting_year_id' => $year->id,
            'status' => 'not_started',
        ]);
        $path = tempnam(sys_get_temp_dir(), 'profile-').'.xlsx';
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->setTitle('1')->setCellValue('A3', 'TABEL UJI');
        (new Xlsx($workbook))->save($path);

        $this->expectExceptionMessage('Tahun Pelaporan sudah memiliki Nilai Indikator');
        try {
            app(WorkbookCatalogImporter::class)->import($path, 2024, true);
        } finally {
            unlink($path);
        }
    }
}
