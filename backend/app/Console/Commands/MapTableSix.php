<?php

namespace App\Console\Commands;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\IndicatorValueRevision;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class MapTableSix extends Command
{
    protected $signature = 'profile:map-table-six {--year=2024} {--workbook= : Path ke workbook Profil Kesehatan}';

    protected $description = 'Petakan Tabel Pelaporan 6 dan impor nilai awal kemampuan Gadar Level I dari Sheet 6';

    private const REGIONS = [
        'BANGKA' => ['Bangka', 'G'],
        'BELITUNG' => ['Belitung', 'M'],
        'BANGKA_BARAT' => ['Bangka Barat', 'S'],
        'BANGKA_TENGAH' => ['Bangka Tengah', 'Y'],
        'BANGKA_SELATAN' => ['Bangka Selatan', 'AE'],
        'BELITUNG_TIMUR' => ['Belitung Timur', 'AK'],
        'PANGKALPINANG' => ['Pangkalpinang', 'AQ'],
    ];

    private const CATEGORIES = [
        'UMUM' => ['Rumah Sakit Umum', 10],
        'KHUSUS' => ['Rumah Sakit Khusus', 11],
    ];

    public function handle(): int
    {
        $year = (int) $this->option('year');
        if ($year !== 2024 && ! $this->option('workbook')) {
            throw new RuntimeException('Tentukan --workbook untuk Tahun Pelaporan selain 2024.');
        }

        $reportingYear = ReportingYear::where('year', $year)->firstOrFail();
        $table = ReportingTable::where('reporting_year_id', $reportingYear->id)->where('code', 'T06')->firstOrFail();
        if ($table->source_sheet && $table->source_sheet !== '6') {
            throw new RuntimeException('Tabel T06 tidak bersumber dari Sheet 6.');
        }

        $path = $this->option('workbook') ?: base_path('../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx');
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || filesize($path) > 25 * 1024 * 1024) {
            throw new RuntimeException('Workbook Sheet 6 harus .xlsx dengan ukuran maksimal 25 MB.');
        }

        $source = [];
        foreach (self::validateWorkbook($path) as $code => $values) {
            $region = Region::where('code', $code)->firstOrFail();
            $source[$region->id] = $values;
        }

        $created = 0;
        $conflicts = [];
        $skipped = [];
        DB::transaction(function () use ($table, $source, $year, &$created, &$conflicts, &$skipped) {
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
            $table->update(['source_sheet' => '6', 'mapping_status' => 'ready']);
            $indicatorIds = $table->indicators()->where('value_kind', 'base')->pluck('id', 'code');

            foreach ($source as $regionId => $values) {
                $submission = Submission::firstOrCreate([
                    'region_id' => $regionId,
                    'reporting_year_id' => $table->reporting_year_id,
                    'reporting_table_id' => $table->id,
                ], ['status' => 'not_started', 'version' => 0]);

                foreach ($values as $code => [$number, $cell]) {
                    $indicatorId = $indicatorIds[$code];
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
                        'reason' => "Nilai awal dari Sheet 6 sel {$cell}, workbook Profil Kesehatan {$year}.",
                    ]);
                    $created++;
                }
            }
        });

        $this->info("Tabel 6 siap: 4 Nilai Dasar dan 5 Nilai Turunan; {$created} nilai awal ditambahkan.");
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
        if (! in_array('6', $reader->listWorksheetNames($path), true)) {
            throw new RuntimeException('Sheet 6 tidak ditemukan.');
        }
        $reader->setLoadSheetsOnly(['6']);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return ($row === 3 && $columnAddress === 'A') || ($row >= 6 && $row <= 13 && in_array($columnAddress, ['A', 'B', 'C', 'D', 'E', 'G', 'H', 'I', 'J', 'K', 'M', 'N', 'O', 'P', 'Q', 'S', 'T', 'U', 'V', 'W', 'Y', 'Z', 'AA', 'AB', 'AC', 'AE', 'AF', 'AG', 'AH', 'AI', 'AK', 'AL', 'AM', 'AN', 'AO', 'AQ', 'AR', 'AS', 'AT', 'AU'], true));
            }
        });

        $book = $reader->load($path);
        try {
            $sheet = $book->getSheetByName('6') ?? throw new RuntimeException('Sheet 6 tidak ditemukan.');

            return self::validateSheet($sheet);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public static function validateSheet(Worksheet $sheet): array
    {
        $title = self::normalize((string) $sheet->getCell('A3')->getValue());
        if (! str_contains($title, self::normalize('PERSENTASE RUMAH SAKIT DENGAN KEMAMPUAN PELAYANAN GAWAT DARURAT (GADAR) LEVEL I'))) {
            throw new RuntimeException('Judul Sheet 6 tidak sesuai dengan kemampuan Gadar Level I.');
        }

        $source = array_fill_keys(array_keys(self::REGIONS), []);
        foreach (self::REGIONS as $regionCode => [$regionName, $startColumn]) {
            if (self::normalize((string) $sheet->getCell("{$startColumn}6")->getValue()) !== self::normalize($regionCode)) {
                throw new RuntimeException("Header wilayah Sheet 6 {$startColumn}6 tidak sesuai: {$regionName}.");
            }

            $startIndex = Coordinate::columnIndexFromString($startColumn);
            $countColumn = Coordinate::stringFromColumnIndex($startIndex + 2);
            $capableColumn = Coordinate::stringFromColumnIndex($startIndex + 3);
            $percentColumn = Coordinate::stringFromColumnIndex($startIndex + 4);
            if (! str_starts_with(self::normalize((string) $sheet->getCell("{$countColumn}7")->getValue()), 'JUMLAH')
                || ! str_contains(self::normalize((string) $sheet->getCell("{$capableColumn}7")->getValue()), 'GADAR')
                || ! str_contains(self::normalize((string) $sheet->getCell("{$capableColumn}7")->getValue()), 'LEVELI')
                || self::normalize((string) $sheet->getCell("{$capableColumn}8")->getValue()) !== 'JUMLAH'
                || ! in_array(trim((string) $sheet->getCell("{$percentColumn}8")->getValue()), ['%', ''], true)) {
                throw new RuntimeException("Header jumlah/Gadar Sheet 6 wilayah {$regionName} tidak sesuai.");
            }

            foreach (self::CATEGORIES as $key => [$label, $row]) {
                $categoryColumn = Coordinate::stringFromColumnIndex($startIndex + 1);
                if (self::normalize((string) $sheet->getCell("{$categoryColumn}{$row}")->getValue()) !== self::normalize($label)) {
                    throw new RuntimeException("Kategori Sheet 6 sel {$categoryColumn}{$row} tidak sesuai: {$label}.");
                }

                foreach ([
                    "RS_{$key}_JUMLAH" => $countColumn,
                    "RS_{$key}_MAMPU_GADAR_LEVEL_I" => $capableColumn,
                ] as $code => $column) {
                    $cell = "{$column}{$row}";
                    $sourceCell = $sheet->getCell($cell);
                    $value = $sourceCell->getValue();
                    if ($sourceCell->getDataType() === 'f' || $value === null || $value === '' || (is_string($value) && trim($value) === '')) {
                        continue;
                    }
                    if (! is_numeric($value) || (float) $value < 0 || (int) $value != (float) $value) {
                        throw new RuntimeException("Nilai {$cell} harus bilangan bulat non-negatif atau kosong.");
                    }
                    $source[$regionCode][$code] = [(int) $value, $cell];
                }
            }
        }

        return $source;
    }

    public static function definitions(): array
    {
        $definitions = [];
        $baseCodes = [];
        foreach (self::CATEGORIES as $key => [$label]) {
            $countCode = "RS_{$key}_JUMLAH";
            $capableCode = "RS_{$key}_MAMPU_GADAR_LEVEL_I";
            $percentCode = "RS_{$key}_PERSENTASE_GADAR_LEVEL_I";
            $baseCodes[$key] = ['count' => $countCode, 'capable' => $capableCode];
            foreach ([
                $countCode => ["{$label} — Jumlah", 'JUMLAH', 'rumah sakit'],
                $capableCode => ["{$label} — Mampu Pelayanan Gadar Level I", 'MAMPU_GADAR_LEVEL_I', 'rumah sakit'],
            ] as $code => [$name, $metric, $unit]) {
                $definitions[$code] = self::indicator($code, $name, 'base', null, [
                    'row_key' => $key,
                    'facility' => $label,
                    'metric' => $metric,
                    'section' => 'Rumah Sakit',
                ], $unit, 0, true);
            }
            $definitions[$percentCode] = self::indicator($percentCode, "{$label} — Persentase Mampu Gadar Level I", 'derived', [
                'op' => 'percent', 'args' => [$capableCode, $countCode],
            ], ['row_key' => $key, 'facility' => $label, 'metric' => 'PERSENTASE_GADAR_LEVEL_I', 'section' => 'Rumah Sakit'], '%', 2, false);
        }

        $definitions['RS_TOTAL_JUMLAH'] = self::indicator('RS_TOTAL_JUMLAH', 'Jumlah Seluruh Rumah Sakit', 'derived', [
            'op' => 'add', 'args' => array_column($baseCodes, 'count'),
        ], ['row_key' => 'TOTAL', 'facility' => 'Jumlah Seluruh Rumah Sakit', 'metric' => 'JUMLAH', 'section' => 'Rumah Sakit'], 'rumah sakit', 0, false);
        $definitions['RS_TOTAL_MAMPU_GADAR_LEVEL_I'] = self::indicator('RS_TOTAL_MAMPU_GADAR_LEVEL_I', 'Jumlah Rumah Sakit Mampu Gadar Level I', 'derived', [
            'op' => 'add', 'args' => array_column($baseCodes, 'capable'),
        ], ['row_key' => 'TOTAL', 'facility' => 'Jumlah Seluruh Rumah Sakit', 'metric' => 'MAMPU_GADAR_LEVEL_I', 'section' => 'Rumah Sakit'], 'rumah sakit', 0, false);
        $definitions['RS_TOTAL_PERSENTASE_GADAR_LEVEL_I'] = self::indicator('RS_TOTAL_PERSENTASE_GADAR_LEVEL_I', 'Persentase Seluruh Rumah Sakit Mampu Gadar Level I', 'derived', [
            'op' => 'percent', 'args' => ['RS_TOTAL_MAMPU_GADAR_LEVEL_I', 'RS_TOTAL_JUMLAH'],
        ], ['row_key' => 'TOTAL', 'facility' => 'Jumlah Seluruh Rumah Sakit', 'metric' => 'PERSENTASE_GADAR_LEVEL_I', 'section' => 'Rumah Sakit'], '%', 2, false);

        return $definitions;
    }

    private static function indicator(string $code, string $name, string $kind, ?array $formula, array $metadata, string $unit, int $decimalPlaces, bool $required): array
    {
        return [
            'code' => $code,
            'name' => $name,
            'data_type' => 'numeric',
            'value_kind' => $kind,
            'formula' => $formula,
            'categories' => $metadata,
            'unit' => $unit,
            'decimal_places' => $decimalPlaces,
            'is_required' => $required,
            'is_active' => true,
        ];
    }

    private static function normalize(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(preg_replace('/\s+/u', ' ', trim($value))));
    }
}
