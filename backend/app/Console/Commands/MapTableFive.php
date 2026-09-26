<?php

namespace App\Console\Commands;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\IndicatorValueRevision;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\Submission;
use App\Models\TableFiveProvinceDenominator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class MapTableFive extends Command
{
    protected $signature = 'profile:map-table-five {--year=2024} {--workbook= : Path ke workbook Profil Kesehatan}';

    protected $description = 'Petakan Tabel Pelaporan 5 dan isi nilai awal kunjungan dari Sheet 5';

    private const REGIONS = [
        'BANGKA' => 'Bangka',
        'BELITUNG' => 'Belitung',
        'BANGKA_BARAT' => 'Bangka Barat',
        'BANGKA_TENGAH' => 'Bangka Tengah',
        'BANGKA_SELATAN' => 'Bangka Selatan',
        'BELITUNG_TIMUR' => 'Belitung Timur',
        'PANGKALPINANG' => 'Pangkalpinang',
    ];

    private const FACILITIES = [
        'PUSKESMAS' => ['Puskesmas', 'Fasilitas Pelayanan Kesehatan Tingkat Pertama', 15],
        'KLINIK_PRATAMA' => ['Klinik Pratama', 'Fasilitas Pelayanan Kesehatan Tingkat Pertama', 24],
        'PRAKTIK_MANDIRI_DOKTER' => ['Praktik Mandiri Dokter', 'Fasilitas Pelayanan Kesehatan Tingkat Pertama', 33],
        'PRAKTIK_MANDIRI_DOKTER_GIGI' => ['Praktik Mandiri Dokter Gigi', 'Fasilitas Pelayanan Kesehatan Tingkat Pertama', 42],
        'PRAKTIK_MANDIRI_BIDAN' => ['Praktik Mandiri Bidan', 'Fasilitas Pelayanan Kesehatan Tingkat Pertama', 51],
        'KLINIK_UTAMA' => ['Klinik Utama', 'Fasilitas Pelayanan Kesehatan Tingkat Lanjut', 62],
        'RS_UMUM' => ['RS Umum', 'Fasilitas Pelayanan Kesehatan Tingkat Lanjut', 71],
        'RS_KHUSUS' => ['RS Khusus', 'Fasilitas Pelayanan Kesehatan Tingkat Lanjut', 80],
        'PRAKTIK_MANDIRI_DOKTER_SPESIALIS' => ['Praktik Mandiri Dokter Spesialis', 'Fasilitas Pelayanan Kesehatan Tingkat Lanjut', 89],
    ];

    private const SUMMARY_ROWS = [
        59 => 'Subjumlah I',
        97 => 'Subjumlah II',
    ];

    private const MEASURES = [
        'RAWAT_JALAN_L' => ['C', 'Kunjungan Rawat Jalan Laki-laki'],
        'RAWAT_JALAN_P' => ['D', 'Kunjungan Rawat Jalan Perempuan'],
        'RAWAT_INAP_L' => ['F', 'Kunjungan Rawat Inap Laki-laki'],
        'RAWAT_INAP_P' => ['G', 'Kunjungan Rawat Inap Perempuan'],
        'GANGGUAN_JIWA_L' => ['I', 'Kunjungan Gangguan Jiwa Laki-laki'],
        'GANGGUAN_JIWA_P' => ['J', 'Kunjungan Gangguan Jiwa Perempuan'],
    ];

    public function handle(): int
    {
        $year = (int) $this->option('year');
        if ($year !== 2024 && ! $this->option('workbook')) {
            throw new RuntimeException('Tentukan --workbook untuk Tahun Pelaporan selain 2024.');
        }

        $reportingYear = ReportingYear::where('year', $year)->firstOrFail();
        $table = ReportingTable::where('reporting_year_id', $reportingYear->id)->where('code', 'T05')->firstOrFail();
        if ($table->source_sheet && $table->source_sheet !== '5') {
            throw new RuntimeException('Tabel T05 tidak bersumber dari Sheet 5.');
        }

        $path = $this->option('workbook') ?: base_path('../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx');
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || filesize($path) > 25 * 1024 * 1024) {
            throw new RuntimeException('Workbook Sheet 5 harus .xlsx dengan ukuran maksimal 25 MB.');
        }

        $workbook = self::validateWorkbook($path);
        $source = [];
        foreach ($workbook['regions'] as $code => $values) {
            $region = Region::where('code', $code)->firstOrFail();
            $source[$region->id] = $values;
        }

        $created = 0;
        $conflicts = [];
        $skipped = [];
        DB::transaction(function () use ($table, $source, $workbook, $year, &$created, &$conflicts, &$skipped) {
            $definitions = self::definitions();
            $existing = $table->allIndicators()->get()->keyBy('code');
            foreach ($source as $regionId => $_) {
                if (! Submission::where('reporting_table_id', $table->id)->where('region_id', $regionId)->whereIn('status', ['completed', 'verified'])->exists()) {
                    continue;
                }
                foreach ($definitions as $definition) {
                    if (! $existing->has($definition['code'])) {
                        throw new RuntimeException("Submission wilayah {$regionId} sudah selesai, tetapi belum memiliki pemetaan {$definition['code']}. Pemetaan dibatalkan.");
                    }
                }
            }

            $table->allIndicators()->update(['is_active' => false]);
            foreach (array_values($definitions) as $position => $definition) {
                Indicator::updateOrCreate(
                    ['reporting_table_id' => $table->id, 'code' => $definition['code']],
                    [...$definition, 'position' => $position + 1],
                );
            }
            $table->update(['source_sheet' => '5', 'mapping_status' => 'ready']);
            $indicators = $table->indicators()->where('value_kind', 'base')->pluck('id', 'code');

            foreach ($source as $regionId => $values) {
                $submission = Submission::firstOrCreate([
                    'region_id' => $regionId,
                    'reporting_year_id' => $table->reporting_year_id,
                    'reporting_table_id' => $table->id,
                ], ['status' => 'not_started', 'version' => 0]);
                foreach ($values as $code => [$number, $cell]) {
                    $indicatorId = $indicators[$code];
                    $value = IndicatorValue::where('submission_id', $submission->id)->where('indicator_id', $indicatorId)->first();
                    if ($value) {
                        if ($value->not_applicable || $value->numeric_value === null || (float) $value->numeric_value !== (float) $number) {
                            $conflicts[] = "{$regionId}:{$cell}";
                        }

                        continue;
                    }
                    if ($submission->status !== 'not_started') {
                        $skipped[] = "{$regionId}:{$cell}";

                        continue;
                    }

                    $value = IndicatorValue::create([
                        'submission_id' => $submission->id,
                        'indicator_id' => $indicatorId,
                        'numeric_value' => $number,
                    ]);
                    IndicatorValueRevision::create([
                        'indicator_value_id' => $value->id,
                        'user_id' => null,
                        'old_value' => null,
                        'new_value' => ['numeric_value' => $number],
                        'reason' => "Nilai awal dari Sheet 5 sel {$cell}, workbook Profil Kesehatan {$year}.",
                    ]);
                    $created++;
                }
            }

            $denominator = TableFiveProvinceDenominator::firstOrCreate(
                ['reporting_year_id' => $table->reporting_year_id],
                $workbook['denominators'],
            );
            if ($denominator->wasRecentlyCreated) {
                $initialValues = array_filter($workbook['denominators'], fn ($value) => $value !== null);
                if ($initialValues) {
                    DB::table('table_five_province_denominator_revisions')->insert([
                        'denominator_id' => $denominator->id,
                        'user_id' => null,
                        'old_values' => null,
                        'new_values' => json_encode($initialValues),
                        'reason' => "Nilai awal penyebut dari Sheet 5 workbook Profil Kesehatan {$year}.",
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        $this->info('Tabel 5 siap: 54 Nilai Dasar dan 54 Nilai Turunan; '.$created.' nilai awal ditambahkan.');
        if ($conflicts) {
            $this->warn(count($conflicts).' nilai berbeda/tidak berlaku dipertahankan: '.implode(', ', $conflicts));
        }
        if ($skipped) {
            $this->warn(count($skipped).' nilai tidak ditambahkan karena submission sudah selesai/terverifikasi: '.implode(', ', $skipped));
        }

        return self::SUCCESS;
    }

    public static function validateWorkbook(string $path): array
    {
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || filesize($path) > 25 * 1024 * 1024) {
            throw new RuntimeException('Workbook harus .xlsx dengan ukuran maksimal 25 MB.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        if (! in_array('5', $reader->listWorksheetNames($path), true)) {
            throw new RuntimeException('Sheet 5 tidak ditemukan.');
        }
        $reader->setLoadSheetsOnly(['5']);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row >= 6 && $row <= 97 && in_array($columnAddress, range('A', 'K'), true);
            }
        });
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheetByName('5') ?? throw new RuntimeException('Sheet 5 tidak ditemukan.');

            return self::validateSheet($sheet);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public static function validateSheet(Worksheet $sheet): array
    {
        foreach ([
            'A10' => 'JUMLAH KUNJUNGAN',
            'A11' => 'JUMLAH PENDUDUK KAB/KOTA',
            'C6' => 'JUMLAH KUNJUNGAN',
            'I6' => 'KUNJUNGAN GANGGUAN JIWA',
            'C7' => 'RAWAT JALAN',
            'F7' => 'RAWAT INAP',
            'I7' => 'JUMLAH',
            'C8' => 'L', 'D8' => 'P', 'E8' => 'L+P',
            'F8' => 'L', 'G8' => 'P', 'H8' => 'L+P',
            'I8' => 'L', 'J8' => 'P', 'K8' => 'L+P',
        ] as $cell => $expected) {
            if (self::normalize((string) $sheet->getCell($cell)->getValue()) !== self::normalize($expected)) {
                throw new RuntimeException("Header {$cell} tidak sesuai: {$expected}.");
            }
        }

        $sectionRows = [13 => 'Fasilitas Pelayanan Kesehatan Tingkat Pertama', 60 => 'Fasilitas Pelayanan Kesehatan Tingkat Lanjut'];
        foreach ($sectionRows as $row => $label) {
            if (self::normalize((string) $sheet->getCell("B{$row}")->getValue()) !== self::normalize($label)) {
                throw new RuntimeException("Bagian Sheet 5 baris {$row} tidak sesuai: {$label}.");
            }
        }
        foreach (self::SUMMARY_ROWS as $row => $label) {
            if (self::normalize((string) $sheet->getCell("A{$row}")->getValue()) !== self::normalize($label)) {
                throw new RuntimeException("Label {$label} Sheet 5 baris {$row} tidak sesuai.");
            }
        }

        $source = array_fill_keys(array_keys(self::REGIONS), []);
        $regionOrder = array_keys(self::REGIONS);
        foreach (array_values(self::FACILITIES) as $facilityPosition => [$label, , $startRow]) {
            $key = array_keys(self::FACILITIES)[$facilityPosition];
            $headingRow = $startRow - 1;
            if (self::normalize((string) $sheet->getCell("B{$headingRow}")->getValue()) !== self::normalize($label)) {
                throw new RuntimeException("Jenis fasilitas Sheet 5 baris {$headingRow} tidak sesuai: {$label}.");
            }
            foreach ($regionOrder as $index => $code) {
                $row = $startRow + $index;
                if ((int) $sheet->getCell("A{$row}")->getValue() !== $index + 1
                    || self::normalize((string) $sheet->getCell("B{$row}")->getValue()) !== self::normalize(self::REGIONS[$code])) {
                    throw new RuntimeException("Wilayah Sheet 5 baris {$row} tidak sesuai: ".self::REGIONS[$code].'.');
                }

                foreach (self::MEASURES as $measure => [$column]) {
                    $cell = "{$column}{$row}";
                    $sourceCell = $sheet->getCell($cell);
                    $value = $sourceCell->getValue();
                    if ($sourceCell->getDataType() === 'f' || $value === null || $value === '' || (is_string($value) && trim($value) === '')) {
                        continue;
                    }
                    if (! is_numeric($value) || (float) $value < 0 || (int) $value != (float) $value) {
                        throw new RuntimeException("Nilai {$cell} harus bilangan bulat non-negatif atau kosong.");
                    }
                    $source[$code]["KUNJUNGAN_{$key}_{$measure}"] = [(int) $value, $cell];
                }
            }
        }

        $denominators = [];
        foreach (['C11' => 'outpatient_l', 'D11' => 'outpatient_p', 'F11' => 'inpatient_l', 'G11' => 'inpatient_p'] as $cell => $key) {
            $sourceCell = $sheet->getCell($cell);
            $value = $sourceCell->getValue();
            if ($sourceCell->getDataType() === 'f' || $value === null || $value === '' || (is_string($value) && trim($value) === '')) {
                $denominators[$key] = null;

                continue;
            }
            if (! is_numeric($value) || (float) $value < 0 || (int) $value != (float) $value) {
                throw new RuntimeException("Nilai penyebut {$cell} harus bilangan bulat non-negatif atau kosong.");
            }
            $denominators[$key] = (int) $value;
        }

        return ['regions' => $source, 'denominators' => $denominators];
    }

    public static function definitions(): array
    {
        $definitions = [];
        foreach (array_values(self::FACILITIES) as $facilityPosition => [$label, $section, $startRow]) {
            $key = array_keys(self::FACILITIES)[$facilityPosition];
            $rowNumber = (string) ($facilityPosition < 5 ? $facilityPosition + 1 : $facilityPosition - 4);
            $codes = [];
            foreach (self::MEASURES as $measure => [, $measureLabel]) {
                $code = "KUNJUNGAN_{$key}_{$measure}";
                $codes[$measure] = $code;
                $definitions[$code] = self::indicator($code, "{$label} — {$measureLabel}", 'base', null, [
                    'row_key' => $key,
                    'facility' => $label,
                    'section' => $section,
                    'row_number' => $rowNumber,
                    'metric' => $measure,
                    'metric_label' => $measureLabel,
                ], true);
            }

            foreach ([
                'RAWAT_JALAN_LP' => ['RAWAT_JALAN_L', 'RAWAT_JALAN_P', 'Jumlah Rawat Jalan L+P'],
                'RAWAT_INAP_LP' => ['RAWAT_INAP_L', 'RAWAT_INAP_P', 'Jumlah Rawat Inap L+P'],
                'GANGGUAN_JIWA_LP' => ['GANGGUAN_JIWA_L', 'GANGGUAN_JIWA_P', 'Jumlah Kunjungan Gangguan Jiwa L+P'],
            ] as $measure => [$left, $right, $measureLabel]) {
                $code = "KUNJUNGAN_{$key}_{$measure}";
                $definitions[$code] = self::indicator($code, "{$label} — {$measureLabel}", 'derived', [
                    'op' => 'add',
                    'args' => [$codes[$left], $codes[$right]],
                ], [
                    'row_key' => $key,
                    'facility' => $label,
                    'section' => $section,
                    'row_number' => $rowNumber,
                    'metric' => $measure,
                    'metric_label' => $measureLabel,
                ], false);
            }
        }

        $summaryGroups = [
            'SUBTOTAL_I' => ['Subjumlah I', 'Fasilitas Pelayanan Kesehatan Tingkat Pertama', array_slice(array_keys(self::FACILITIES), 0, 5)],
            'SUBTOTAL_II' => ['Subjumlah II', 'Fasilitas Pelayanan Kesehatan Tingkat Lanjut', array_slice(array_keys(self::FACILITIES), 5)],
            'TOTAL_UTAMA' => ['Jumlah Utama', 'Seluruh fasilitas pelayanan kesehatan', array_keys(self::FACILITIES)],
        ];
        foreach ($summaryGroups as $group => [$label, $section, $facilityKeys]) {
            $summaryCodes = [];
            foreach (array_keys(self::MEASURES) as $measure) {
                $code = "KUNJUNGAN_{$group}_{$measure}";
                $summaryCodes[$measure] = $code;
                $definitions[$code] = self::indicator($code, "{$label} — ".self::MEASURES[$measure][1], 'derived', [
                    'op' => 'add',
                    'args' => array_map(fn (string $facility) => "KUNJUNGAN_{$facility}_{$measure}", $facilityKeys),
                ], [
                    'row_key' => $group,
                    'facility' => $label,
                    'section' => $section,
                    'metric' => $measure,
                    'metric_label' => self::MEASURES[$measure][1],
                ], false);
            }
            foreach ([
                'RAWAT_JALAN_LP' => ['RAWAT_JALAN_L', 'RAWAT_JALAN_P', 'Jumlah Rawat Jalan L+P'],
                'RAWAT_INAP_LP' => ['RAWAT_INAP_L', 'RAWAT_INAP_P', 'Jumlah Rawat Inap L+P'],
                'GANGGUAN_JIWA_LP' => ['GANGGUAN_JIWA_L', 'GANGGUAN_JIWA_P', 'Jumlah Kunjungan Gangguan Jiwa L+P'],
            ] as $measure => [$left, $right, $measureLabel]) {
                $code = "KUNJUNGAN_{$group}_{$measure}";
                $definitions[$code] = self::indicator($code, "{$label} — {$measureLabel}", 'derived', [
                    'op' => 'add',
                    'args' => [$summaryCodes[$left], $summaryCodes[$right]],
                ], [
                    'row_key' => $group,
                    'facility' => $label,
                    'section' => $section,
                    'metric' => $measure,
                    'metric_label' => $measureLabel,
                ], false);
            }
        }

        return $definitions;
    }

    private static function indicator(string $code, string $name, string $kind, ?array $formula, array $metadata, bool $required): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'data_type' => 'numeric',
            'value_kind' => $kind,
            'formula' => $formula,
            'categories' => $metadata,
            'unit' => 'kunjungan',
            'decimal_places' => 0,
            'is_required' => $required,
            'is_active' => true,
        ];
    }

    private static function normalize(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(preg_replace('/\s+/u', ' ', trim($value))));
    }
}
