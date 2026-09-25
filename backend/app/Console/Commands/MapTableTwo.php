<?php

namespace App\Console\Commands;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\IndicatorValueRevision;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\Submission;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;

class MapTableTwo extends Command
{
    protected $signature = 'profile:map-table-two {--year=2024} {--workbook= : Path ke workbook Profil Kesehatan}';

    protected $description = 'Petakan Tabel Pelaporan 2 dan isi nilai awal penduduk menurut umur dan jenis kelamin';

    private const AGES = [
        '0_4' => '0 - 4', '5_9' => '5 - 9', '10_14' => '10 - 14', '15_19' => '15 - 19',
        '20_24' => '20 - 24', '25_29' => '25 - 29', '30_34' => '30 - 34', '35_39' => '35 - 39',
        '40_44' => '40 - 44', '45_49' => '45 - 49', '50_54' => '50 - 54', '55_59' => '55 - 59',
        '60_64' => '60 - 64', '65_69' => '65 - 69', '70_74' => '70 - 74', '75_PLUS' => '75+',
    ];

    private const REGIONS = [
        'BANGKA' => ['H', 'I', 'J', 'K'],
        'BELITUNG' => ['O', 'P', 'Q', 'R'],
        'BANGKA_BARAT' => ['V', 'W', 'X', 'Y'],
        'BANGKA_TENGAH' => ['AC', 'AD', 'AE', 'AF'],
        'BANGKA_SELATAN' => ['AJ', 'AK', 'AL', 'AM'],
        'BELITUNG_TIMUR' => ['AQ', 'AR', 'AS', 'AT'],
        'PANGKALPINANG' => ['AX', 'AY', 'AZ', 'BA'],
    ];

    public function handle(): int
    {
        if ((int) $this->option('year') !== 2024 && ! $this->option('workbook')) {
            throw new RuntimeException('Tentukan --workbook untuk Tahun Pelaporan selain 2024.');
        }
        $table = ReportingTable::where('code', 'T02')
            ->whereHas('reportingYear', fn ($query) => $query->where('year', (int) $this->option('year')))
            ->firstOrFail();
        if ($table->source_sheet && $table->source_sheet !== '2') {
            throw new RuntimeException('Tabel T02 tidak bersumber dari Sheet 2.');
        }
        $path = $this->option('workbook') ?: base_path('../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx');
        if (! is_file($path)) {
            throw new RuntimeException('Workbook Sheet 2 tidak ditemukan.');
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || filesize($path) > 25 * 1024 * 1024) {
            throw new RuntimeException('Workbook harus .xlsx dengan ukuran maksimal 25 MB.');
        }

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $reader->setLoadSheetsOnly(['2']);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row === 6 || ($row >= 11 && $row <= 26);
            }
        });
        $book = $reader->load($path);
        $sheet = $book->getSheetByName('2') ?? throw new RuntimeException('Sheet 2 tidak ditemukan.');
        $source = [];
        try {
            foreach (self::REGIONS as $code => [$header, $ageColumn, $maleColumn, $femaleColumn]) {
                $region = Region::where('code', $code)->firstOrFail();
                $heading = strtoupper(trim((string) $sheet->getCell("{$header}6")->getValue()));
                if (str_replace(' ', '_', $heading) !== $code) {
                    throw new RuntimeException("Header {$header}6 tidak sesuai wilayah {$code}.");
                }
                $row = 11;
                foreach (self::AGES as $index => $age) {
                    $label = preg_replace('/\s*[-–]\s*/u', ' - ', trim((string) $sheet->getCell("{$ageColumn}{$row}")->getValue()));
                    if ($label !== $age) {
                        throw new RuntimeException("Kelompok umur {$ageColumn}{$row} tidak sesuai: {$label}.");
                    }
                    foreach (['L' => $maleColumn, 'P' => $femaleColumn] as $sex => $column) {
                        $cell = "{$column}{$row}";
                        $number = $sheet->getCell($cell)->getValue();
                        if (! is_numeric($number) || (float) $number < 0 || (int) $number != (float) $number) {
                            throw new RuntimeException("Nilai {$cell} harus bilangan bulat non-negatif.");
                        }
                        $source[$region->id]["PENDUDUK_{$index}_{$sex}"] = [(int) $number, $cell];
                    }
                    $row++;
                }
            }
        } finally {
            $book->disconnectWorksheets();
        }

        $created = 0;
        $conflicts = [];
        $skipped = [];
        DB::transaction(function () use ($table, $source, &$created, &$conflicts, &$skipped) {
            $existing = $table->allIndicators()->get()->keyBy('code');
            foreach ($source as $regionId => $values) {
                $locked = Submission::where('reporting_table_id', $table->id)->where('region_id', $regionId)->whereIn('status', ['completed', 'verified'])->exists();
                if (! $locked) {
                    continue;
                }
                foreach (array_keys($values) as $code) {
                    if (! $existing->has($code)) {
                        throw new RuntimeException("Submission wilayah {$regionId} sudah selesai, tetapi belum memiliki pemetaan {$code}. Pemetaan dibatalkan agar status tidak berubah makna.");
                    }
                }
            }
            $table->allIndicators()->update(['is_active' => false]);
            $position = 1;
            $maleCodes = $femaleCodes = $totalCodes = [];
            foreach (self::AGES as $key => $age) {
                $male = "PENDUDUK_{$key}_L";
                $female = "PENDUDUK_{$key}_P";
                $total = "PENDUDUK_{$key}_TOTAL";
                $maleCodes[] = $male;
                $femaleCodes[] = $female;
                $totalCodes[] = $total;
                $this->indicator($table, $position++, $male, 'Jumlah Penduduk Laki-laki', 'base', ['kelompok_umur' => $age, 'jenis_kelamin' => 'Laki-laki']);
                $this->indicator($table, $position++, $female, 'Jumlah Penduduk Perempuan', 'base', ['kelompok_umur' => $age, 'jenis_kelamin' => 'Perempuan']);
                $this->indicator($table, $position++, $total, 'Jumlah Penduduk Laki-laki+Perempuan', 'derived', ['kelompok_umur' => $age, 'jenis_kelamin' => 'L+P'], ['op' => 'add', 'args' => [$male, $female]]);
                $this->indicator($table, $position++, "PENDUDUK_{$key}_RASIO", 'Rasio Jenis Kelamin', 'derived', ['kelompok_umur' => $age, 'jenis_kelamin' => 'Rasio'], ['op' => 'percent', 'args' => [$male, $female]], 'per 100 perempuan', 1);
            }
            $summary = ['kelompok_umur' => 'Semua Umur'];
            $this->indicator($table, $position++, 'PENDUDUK_TOTAL_L', 'Jumlah Penduduk Laki-laki', 'derived', $summary + ['jenis_kelamin' => 'Laki-laki'], ['op' => 'add', 'args' => $maleCodes]);
            $this->indicator($table, $position++, 'PENDUDUK_TOTAL_P', 'Jumlah Penduduk Perempuan', 'derived', $summary + ['jenis_kelamin' => 'Perempuan'], ['op' => 'add', 'args' => $femaleCodes]);
            $this->indicator($table, $position++, 'PENDUDUK_TOTAL', 'Jumlah Penduduk Laki-laki+Perempuan', 'derived', $summary + ['jenis_kelamin' => 'L+P'], ['op' => 'add', 'args' => $totalCodes]);
            $this->indicator($table, $position++, 'PENDUDUK_TOTAL_RASIO', 'Rasio Jenis Kelamin', 'derived', $summary + ['jenis_kelamin' => 'Rasio'], ['op' => 'percent', 'args' => ['PENDUDUK_TOTAL_L', 'PENDUDUK_TOTAL_P']], 'per 100 perempuan', 1);
            $dependent = array_merge(array_slice($totalCodes, 0, 3), array_slice($totalCodes, 13));
            $productive = array_slice($totalCodes, 3, 10);
            $this->indicator($table, $position, 'ANGKA_BEBAN_TANGGUNGAN', 'Angka Beban Tanggungan', 'derived', $summary + ['jenis_kelamin' => 'Beban Tanggungan'], ['op' => 'percent', 'args' => [['op' => 'add', 'args' => $dependent], ['op' => 'add', 'args' => $productive]]], 'per 100 usia produktif', 2);
            $table->update(['mapping_status' => 'ready']);

            $ids = $table->indicators()->where('value_kind', 'base')->pluck('id', 'code');
            foreach ($source as $regionId => $values) {
                $submission = Submission::firstOrCreate([
                    'region_id' => $regionId, 'reporting_year_id' => $table->reporting_year_id, 'reporting_table_id' => $table->id,
                ], ['status' => 'not_started', 'version' => 0]);
                foreach ($values as $code => [$number, $cell]) {
                    $existing = IndicatorValue::where('submission_id', $submission->id)->where('indicator_id', $ids[$code])->first();
                    if ($existing) {
                        if ($existing->not_applicable || $existing->numeric_value === null || (float) $existing->numeric_value !== (float) $number) {
                            $conflicts[] = "{$regionId}:{$cell}";
                        }

                        continue;
                    }
                    if ($submission->status !== 'not_started') {
                        $skipped[] = "{$regionId}:{$cell}";

                        continue;
                    }
                    $value = IndicatorValue::create(['submission_id' => $submission->id, 'indicator_id' => $ids[$code], 'numeric_value' => $number]);
                    IndicatorValueRevision::create([
                        'indicator_value_id' => $value->id, 'user_id' => null, 'old_value' => null,
                        'new_value' => ['numeric_value' => $number],
                        'reason' => "Nilai awal dari Sheet 2, sel {$cell}, workbook Profil Kesehatan {$this->option('year')}.",
                    ]);
                    $created++;
                }
            }
        });

        $this->info("Tabel 2 siap: 32 Nilai Dasar, 37 Nilai Turunan; {$created} nilai awal ditambahkan.");
        if ($conflicts) {
            $this->warn(count($conflicts).' nilai lama berbeda/tidak berlaku, tetap dipertahankan: '.implode(', ', $conflicts));
        }
        if ($skipped) {
            $this->warn(count($skipped).' nilai tidak ditambahkan karena submission sudah selesai/terverifikasi: '.implode(', ', $skipped));
        }

        return self::SUCCESS;
    }

    private function indicator(ReportingTable $table, int $position, string $code, string $name, string $kind, array $categories, ?array $formula = null, string $unit = 'jiwa', int $decimals = 0): void
    {
        Indicator::updateOrCreate(['reporting_table_id' => $table->id, 'code' => $code], [
            'name' => $name, 'data_type' => 'numeric', 'value_kind' => $kind, 'formula' => $formula,
            'categories' => $categories, 'unit' => $unit, 'decimal_places' => $decimals,
            'is_required' => $kind === 'base', 'is_active' => true, 'position' => $position,
        ]);
    }
}
