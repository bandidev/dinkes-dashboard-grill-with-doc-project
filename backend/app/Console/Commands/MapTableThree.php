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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use RuntimeException;

class MapTableThree extends Command
{
    protected $signature = 'profile:map-table-three {--year=2024} {--workbook= : Path ke workbook Profil Kesehatan}';

    protected $description = 'Petakan Tabel Pelaporan 3 dan isi nilai awal pendidikan dari Sheet 3';

    private const REGIONS = [
        'BANGKA' => ['J', 'K', 'L', 'M'],
        'BELITUNG' => ['S', 'T', 'U', 'V'],
        'BANGKA_BARAT' => ['AB', 'AC', 'AD', 'AE'],
        'BANGKA_TENGAH' => ['AK', 'AL', 'AM', 'AN'],
        'BANGKA_SELATAN' => ['AT', 'AU', 'AV', 'AW'],
        'BELITUNG_TIMUR' => ['BC', 'BD', 'BE', 'BF'],
        'PANGKALPINANG' => ['BL', 'BM', 'BN', 'BO'],
    ];

    private const ROWS = [
        'PENDUDUK_15_PLUS' => [11, 'Penduduk berumur 15 tahun ke atas', 'population'],
        'MELEK_HURUF' => [12, 'Penduduk berumur 15 tahun ke atas yang melek huruf', 'literacy'],
        'TIDAK_MEMILIKI_IJAZAH_SD' => [14, 'Tidak memiliki ijazah SD', 'education'],
        'SD_MI' => [15, 'SD/MI', 'education'],
        'SMP_MTS' => [16, 'SMP/MTs', 'education'],
        'SMA_MA' => [17, 'SMA/MA', 'education'],
        'SEKOLAH_MENENGAH_KEJURUAN' => [18, 'Sekolah menengah kejuruan', 'education'],
        'DIPLOMA_I_II' => [19, 'Diploma I/Diploma II', 'education'],
        'AKADEMI_DIPLOMA_III' => [20, 'Akademi/Diploma III', 'education'],
        'S1_DIPLOMA_IV' => [21, 'S1/Diploma IV', 'education'],
        'S2_S3' => [22, 'S2/S3 (Master/Doktor)', 'education'],
    ];

    private const T02_AGES_15_PLUS = [
        '15_19', '20_24', '25_29', '30_34', '35_39', '40_44', '45_49',
        '50_54', '55_59', '60_64', '65_69', '70_74', '75_PLUS',
    ];

    public function handle(): int
    {
        $year = (int) $this->option('year');
        if ($year !== 2024 && ! $this->option('workbook')) {
            throw new RuntimeException('Tentukan --workbook untuk Tahun Pelaporan selain 2024.');
        }

        $reportingYear = ReportingYear::where('year', $year)->firstOrFail();
        $table = ReportingTable::where('reporting_year_id', $reportingYear->id)->where('code', 'T03')->firstOrFail();
        if ($table->source_sheet && $table->source_sheet !== '3') {
            throw new RuntimeException('Tabel T03 tidak bersumber dari Sheet 3.');
        }

        $path = $this->option('workbook') ?: base_path('../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx');
        if (! is_file($path)) {
            throw new RuntimeException('Workbook Sheet 3 tidak ditemukan.');
        }
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || filesize($path) > 25 * 1024 * 1024) {
            throw new RuntimeException('Workbook harus .xlsx dengan ukuran maksimal 25 MB.');
        }

        $source = [];
        foreach (self::validateWorkbook($path) as $code => $values) {
            $region = Region::where('code', $code)->firstOrFail();
            $source[$region->id] = $values;
        }

        $bangkaBarat = Region::where('code', 'BANGKA_BARAT')->firstOrFail();
        foreach (['L', 'P'] as $sex) {
            $population = $this->tableTwoPopulation($reportingYear, $bangkaBarat, $sex);
            if ($population !== null) {
                $source[$bangkaBarat->id]["PENDUDUK_15_PLUS_{$sex}"] = [$population, 'Tabel 2 nilai dasar usia 15 tahun ke atas'];
            }
        }

        $created = 0;
        $conflicts = [];
        $skipped = [];
        DB::transaction(function () use ($table, $source, $path, $year, &$created, &$conflicts, &$skipped) {
            [$definitions, $derivedSources] = self::definitions();
            $existing = $table->allIndicators()->get()->keyBy('code');
            foreach ($source as $regionId => $_) {
                $locked = Submission::where('reporting_table_id', $table->id)
                    ->where('region_id', $regionId)
                    ->whereIn('status', ['completed', 'verified'])
                    ->exists();
                if ($locked) {
                    foreach ($definitions as $definition) {
                        $code = $definition['code'];
                        if (! $existing->has($code)) {
                            throw new RuntimeException("Submission wilayah {$regionId} sudah selesai, tetapi belum memiliki pemetaan {$code}. Pemetaan dibatalkan.");
                        }
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
            foreach ($table->allIndicators()->where('value_kind', 'derived')->whereNotIn('code', $derivedSources)->get() as $indicator) {
                $indicator->update(['is_active' => false]);
            }
            $table->update(['mapping_status' => 'ready']);
            $indicators = $table->indicators()->where('value_kind', 'base')->pluck('id', 'code');

            foreach ($source as $regionId => $values) {
                $submission = Submission::firstOrCreate([
                    'region_id' => $regionId,
                    'reporting_year_id' => $table->reporting_year_id,
                    'reporting_table_id' => $table->id,
                ], ['status' => 'not_started', 'version' => 0]);
                foreach ($values as $code => [$number, $origin]) {
                    $indicatorId = $indicators[$code];
                    $value = IndicatorValue::where('submission_id', $submission->id)->where('indicator_id', $indicatorId)->first();
                    if ($value) {
                        if ($value->not_applicable || $value->numeric_value === null || (float) $value->numeric_value !== (float) $number) {
                            $conflicts[] = "{$regionId}:{$code}";
                        }

                        continue;
                    }
                    if ($submission->status !== 'not_started') {
                        $skipped[] = "{$regionId}:{$code}";

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
                        'reason' => $origin === 'Tabel 2 nilai dasar usia 15 tahun ke atas'
                            ? "Nilai awal dihitung dari Tabel 2 untuk Tahun Pelaporan {$year}."
                            : "Nilai awal dari {$origin}, workbook ".basename($path)." Tahun Pelaporan {$year}.",
                    ]);
                    $created++;
                }
            }
        });

        $this->info("Tabel 3 siap: 22 Nilai Dasar dan 41 Nilai Turunan; {$created} nilai awal ditambahkan.");
        if ($conflicts) {
            $this->warn(count($conflicts).' nilai berbeda/tidak berlaku dipertahankan: '.implode(', ', $conflicts));
        }
        if ($skipped) {
            $this->warn(count($skipped).' nilai tidak ditambahkan karena submission sudah selesai/terverifikasi: '.implode(', ', $skipped));
        }

        return self::SUCCESS;
    }

    public function tableTwoPopulation(ReportingYear $year, Region $region, string $sex): ?int
    {
        $table = ReportingTable::with('indicators')
            ->where('reporting_year_id', $year->id)
            ->where('code', 'T02')
            ->where('mapping_status', 'ready')
            ->first();
        if (! $table) {
            return null;
        }
        if (! $table->indicators->contains(fn (Indicator $indicator) => $indicator->code === 'PENDUDUK_15_19_L' && $indicator->value_kind === 'base')) {
            return null;
        }

        $indicators = $table->indicators
            ->filter(fn (Indicator $indicator) => $indicator->value_kind === 'base' && preg_match('/^PENDUDUK_(.+)_'.preg_quote($sex, '/').'$/', $indicator->code, $matches) && in_array($matches[1], self::T02_AGES_15_PLUS, true))
            ->keyBy('code');
        if ($indicators->count() !== count(self::T02_AGES_15_PLUS) || ! $indicators->has("PENDUDUK_15_19_{$sex}")) {
            return null;
        }

        $submission = Submission::with('values')->where('reporting_table_id', $table->id)->where('region_id', $region->id)->first();
        $values = $submission?->values->keyBy('indicator_id');
        $sum = 0;
        foreach (self::T02_AGES_15_PLUS as $age) {
            $indicator = $indicators->get("PENDUDUK_{$age}_{$sex}");
            $value = $values?->get($indicator->id);
            if (! $value || $value->not_applicable || $value->numeric_value === null) {
                return null;
            }
            $sum += (float) $value->numeric_value;
        }

        return (int) $sum;
    }

    public static function validateSheet(Worksheet $sheet): array
    {
        $source = [];
        foreach (self::REGIONS as $code => [$headerColumn, $variableColumn, $maleColumn, $femaleColumn]) {
            $heading = strtoupper(trim((string) $sheet->getCell("{$headerColumn}7")->getValue()));
            if (str_replace(' ', '_', $heading) !== $code) {
                throw new RuntimeException("Header {$headerColumn}7 tidak sesuai wilayah {$code}.");
            }
            $source[$code] = [];
            foreach (self::ROWS as $key => [$row]) {
                $variable = strtoupper(preg_replace('/\s+/u', ' ', trim((string) $sheet->getCell("{$variableColumn}{$row}")->getValue())));
                if ($variable === '') {
                    throw new RuntimeException("Label variabel {$variableColumn}{$row} kosong.");
                }
                if ($row >= 14 && ! preg_match('/^'.chr(97 + $row - 14).'\s*[,.)]/u', strtolower($variable))) {
                    throw new RuntimeException("Kategori pendidikan {$variableColumn}{$row} tidak sesuai.");
                }
                if ($row === 11 && ! str_contains($variable, 'PENDUDUK BERUMUR 15 TAHUN KE ATAS')) {
                    throw new RuntimeException("Baris {$variableColumn}{$row} bukan penduduk berumur 15 tahun ke atas.");
                }
                if ($row === 12 && ! str_contains($variable, 'MELEK HURUF')) {
                    throw new RuntimeException("Baris {$variableColumn}{$row} bukan penduduk melek huruf.");
                }
                if ($row === 21 && ! str_contains($variable, 'DIPLOMA IV')) {
                    throw new RuntimeException("Baris {$variableColumn}{$row} bukan kategori S1/Diploma IV.");
                }
                foreach (['L' => $maleColumn, 'P' => $femaleColumn] as $sex => $column) {
                    $cell = "{$column}{$row}";
                    $value = $sheet->getCell($cell)->getValue();
                    if ($sheet->getCell($cell)->getDataType() === 'f' || $value === null || $value === '' || (is_string($value) && trim($value) === '0,00')) {
                        continue;
                    }
                    if (! is_numeric($value) || (float) $value < 0 || (int) $value != (float) $value) {
                        throw new RuntimeException("Nilai {$cell} harus bilangan bulat non-negatif atau kosong.");
                    }
                    $source[$code]["{$key}_{$sex}"] = [(int) $value, "Sheet 3 sel {$cell}"];
                }
            }
        }

        return $source;
    }

    public static function validateWorkbook(string $path): array
    {
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || filesize($path) > 25 * 1024 * 1024) {
            throw new RuntimeException('Workbook harus .xlsx dengan ukuran maksimal 25 MB.');
        }
        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(false);
        $reader->setLoadSheetsOnly(['3']);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row === 7 || $row === 11 || $row === 12 || ($row >= 14 && $row <= 22);
            }
        });
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheetByName('3') ?? throw new RuntimeException('Sheet 3 tidak ditemukan.');

            return self::validateSheet($sheet);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public static function definitions(): array
    {
        $definitions = [];
        $derivedSources = [];
        foreach (self::ROWS as $key => [$row, $label, $section]) {
            $categories = ['row_key' => $key, 'row_number' => (string) ($row >= 14 ? $row - 11 : $row - 10), 'row_label' => $row >= 14 ? chr(97 + $row - 14).'. '.$label : $label, 'section' => $section];
            foreach (['L' => 'Laki-laki', 'P' => 'Perempuan'] as $sex => $sexLabel) {
                $code = "{$key}_{$sex}";
                $definitions[] = [
                    'code' => $code, 'name' => $label.' — '.$sexLabel, 'data_type' => 'numeric', 'value_kind' => 'base',
                    'categories' => $categories + ['measure' => 'count', 'sex' => $sex], 'unit' => 'jiwa',
                    'decimal_places' => 0, 'is_required' => true, 'is_active' => true,
                ];
            }
            $totalCode = "{$key}_TOTAL";
            $derivedSources[] = $totalCode;
            $definitions[] = [
                'code' => $totalCode, 'name' => $label.' — Laki-laki+Perempuan', 'data_type' => 'numeric', 'value_kind' => 'derived',
                'formula' => ['op' => 'add', 'args' => ["{$key}_L", "{$key}_P"]],
                'categories' => $categories + ['measure' => 'count', 'sex' => 'TOTAL'], 'unit' => 'jiwa',
                'decimal_places' => 0, 'is_required' => false, 'is_active' => true,
            ];
            if ($section === 'population') {
                continue;
            }
            foreach (['L' => ["{$key}_L", 'Laki-laki'], 'P' => ["{$key}_P", 'Perempuan'], 'TOTAL' => [$totalCode, 'Laki-laki+Perempuan']] as $sex => [$numerator, $sexLabel]) {
                $denominator = $sex === 'TOTAL' ? 'PENDUDUK_15_PLUS_TOTAL' : "PENDUDUK_15_PLUS_{$sex}";
                $definitions[] = [
                    'code' => "{$key}_PERCENT_{$sex}", 'name' => $label.' — Persentase '.$sexLabel, 'data_type' => 'numeric', 'value_kind' => 'derived',
                    'formula' => ['op' => 'percent', 'args' => [$numerator, $denominator]],
                    'categories' => $categories + ['measure' => 'percent', 'sex' => $sex], 'unit' => '%',
                    'decimal_places' => $section === 'literacy' ? 2 : 1, 'is_required' => false, 'is_active' => true,
                ];
                $derivedSources[] = "{$key}_PERCENT_{$sex}";
            }
        }
        $definitions = collect($definitions)->mapWithKeys(fn (array $definition) => [$definition['code'] => $definition])->all();
        $definitions['PENDUDUK_15_PLUS_TOTAL']['formula'] = ['op' => 'add', 'args' => ['PENDUDUK_15_PLUS_L', 'PENDUDUK_15_PLUS_P']];

        return [$definitions, $derivedSources];
    }
}
