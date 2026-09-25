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

class MapTableFour extends Command
{
    protected $signature = 'profile:map-table-four {--year=2024} {--workbook= : Path ke workbook Profil Kesehatan}';

    protected $description = 'Petakan Tabel Pelaporan 4 dan impor nilai awal fasilitas kesehatan dari Sheet 4';

    private const REGIONS = [
        'BANGKA' => ['L', 12],
        'BELITUNG' => ['W', 23],
        'BANGKA_BARAT' => ['AH', 34],
        'BANGKA_TENGAH' => ['AS', 45],
        'BANGKA_SELATAN' => ['BD', 56],
        'BELITUNG_TIMUR' => ['BO', 67],
        'PANGKALPINANG' => ['BZ', 78],
    ];

    private const OWNERS = [
        'KEMENKES' => ['KEMENKES', 'Kemenkes'],
        'PEM_PROV' => ['PEM,PROV', 'Pem. Prov'],
        'PEM_KAB_KOTA' => ['PEM,KAB/KOTA', 'Pem. Kab/Kota'],
        'TNI_POLRI' => ['TNI/POLRI', 'TNI/Polri'],
        'BUMN' => ['BUMN', 'BUMN'],
        'SWASTA' => ['SWASTA', 'Swasta'],
        'ORGANISASI_KEMASYARAKATAN' => ['ORGANISASI KEMASYARAKATAN', 'Organisasi Kemasyarakatan'],
    ];

    private const SECTIONS = [
        10 => ['RUMAH_SAKIT', 'RUMAH SAKIT'],
        13 => ['PUSKESMAS_DAN_JARINGANNYA', 'PUSKESMAS DAN JARINGANNYA'],
        19 => ['SARANA_PELAYANAN_LAIN', 'SARANA PELAYANAN LAIN'],
        31 => ['SARANA_PRODUKSI_DAN_DISTRIBUSI_KEFARMASIAN', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
    ];

    private const ROWS = [
        11 => ['RUMAH_SAKIT_UMUM', 'RUMAH SAKIT UMUM', 'RUMAH SAKIT'],
        12 => ['RUMAH_SAKIT_KHUSUS', 'RUMAH SAKIT KHUSUS', 'RUMAH SAKIT'],
        14 => ['PUSKESMAS_RAWAT_INAP', 'PUSKESMAS RAWAT INAP', 'PUSKESMAS DAN JARINGANNYA'],
        15 => ['PUSKESMAS_TEMPAT_TIDUR', 'JUMLAH TEMPAT TIDUR', 'PUSKESMAS DAN JARINGANNYA'],
        16 => ['PUSKESMAS_NON_RAWAT_INAP', 'PUSKESMAS NON RAWAT INAP', 'PUSKESMAS DAN JARINGANNYA'],
        17 => ['PUSKESMAS_KELILING', 'PUSKESMAS KELILING', 'PUSKESMAS DAN JARINGANNYA'],
        18 => ['PUSKESMAS_PEMBANTU', 'PUSKESMAS PEMBANTU', 'PUSKESMAS DAN JARINGANNYA'],
        20 => ['KLINIK_PRATAMA', 'KLINIK PRATAMA', 'SARANA PELAYANAN LAIN'],
        21 => ['KLINIK_UTAMA', 'KLINIK UTAMA', 'SARANA PELAYANAN LAIN'],
        22 => ['PRAKTIK_MANDIRI_DOKTER', 'TEMPAT PRAKTIK MANDIRI DOKTER', 'SARANA PELAYANAN LAIN'],
        23 => ['PRAKTIK_MANDIRI_DOKTER_GIGI', 'TEMPAT PRAKTIK MANDIRI DOKTER GIGI', 'SARANA PELAYANAN LAIN'],
        24 => ['PRAKTIK_MANDIRI_DOKTER_SPESIALIS', 'TEMPAT PRAKTIK MANDIRI DOKTER SPESIALIS', 'SARANA PELAYANAN LAIN'],
        25 => ['PRAKTIK_MANDIRI_BIDAN', 'TEMPAT PRAKTIK MANDIRI BIDAN', 'SARANA PELAYANAN LAIN'],
        26 => ['PRAKTIK_MANDIRI_PERAWAT', 'TEMPAT PRAKTIK MANDIRI PERAWAT', 'SARANA PELAYANAN LAIN', 'TEMPAT PRAKTK MANDIRI PERAWAT'],
        27 => ['GRIYA_SEHAT', 'GRIYA SEHAT', 'SARANA PELAYANAN LAIN'],
        28 => ['PANTI_SEHAT', 'PANTI SEHAT', 'SARANA PELAYANAN LAIN'],
        29 => ['UNIT_TRANSFUSI_DARAH', 'UNIT TRANSFUSI DARAH', 'SARANA PELAYANAN LAIN'],
        30 => ['LABORATORIUM_KESEHATAN', 'LABORATORIUM KESEHATAN', 'SARANA PELAYANAN LAIN'],
        32 => ['INDUSTRI_FARMASI', 'INDUSTRI FARMASI', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        33 => ['INDUSTRI_OBAT_TRADISIONAL', 'INDUSTRI OBAT TRADISIONAL/EKSTRAK BAHAN ALAM (IOT/IEBA)', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        34 => ['UKOT_UMOT', 'USAHA KECIL/MIKRO OBAT TRADISIONAL (UKOT/UMOT)', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        35 => ['PRODUKSI_ALAT_KESEHATAN', 'PRODUKSI ALAT KESEHATAN', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        36 => ['PRODUKSI_PERBEKALAN_KESEHATAN_RUMAH_TANGGA', 'PRODUKSI PERBEKALAN KESEHATAN RUMAH TANGGA (PKRT)', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        37 => ['INDUSTRI_KOSMETIKA', 'INDUSTRI KOSMETIKA', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        38 => ['PEDAGANG_BESAR_FARMASI', 'PEDAGANG BESAR FARMASI PBF', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        39 => ['PENYALUR_ALAT_KESEHATAN', 'PENYALUR ALAT KESEHATAN (PAK)', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        40 => ['APOTEK', 'APOTEK', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        41 => ['TOKO_OBAT', 'TOKO OBAT', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
        42 => ['TOKO_ALKES', 'TOKO ALKES', 'SARANA PRODUKSI DAN DISTRIBUSI KEFARMASIAN'],
    ];

    public function handle(): int
    {
        $year = (int) $this->option('year');
        if ($year !== 2024 && ! $this->option('workbook')) {
            throw new RuntimeException('Tentukan --workbook untuk Tahun Pelaporan selain 2024.');
        }
        $reportingYear = ReportingYear::where('year', $year)->firstOrFail();
        $table = ReportingTable::where('reporting_year_id', $reportingYear->id)->where('code', 'T04')->firstOrFail();
        if ($table->source_sheet && $table->source_sheet !== '4') {
            throw new RuntimeException('Tabel T04 tidak bersumber dari Sheet 4.');
        }

        $path = $this->option('workbook') ?: base_path('../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx');
        if (! is_file($path) || strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx' || filesize($path) > 25 * 1024 * 1024) {
            throw new RuntimeException('Workbook Sheet 4 harus .xlsx dengan ukuran maksimal 25 MB.');
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
            $table->update(['mapping_status' => 'ready']);
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
                        'reason' => "Nilai awal dari Sheet 4 sel {$cell}, workbook Profil Kesehatan {$year}.",
                    ]);
                    $created++;
                }
            }
        });

        $this->info("Tabel 4 siap: 203 Nilai Dasar dan 29 Nilai Turunan; {$created} nilai awal ditambahkan.");
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
        $reader->setLoadSheetsOnly(['4']);
        $reader->setReadFilter(new class implements IReadFilter
        {
            public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
            {
                return $row === 6 || $row === 7 || $row === 8 || in_array($row, [10, 13, 19, 31], true) || ($row >= 11 && $row <= 42);
            }
        });
        $book = $reader->load($path);
        try {
            $sheet = $book->getSheetByName('4') ?? throw new RuntimeException('Sheet 4 tidak ditemukan.');

            return self::validateSheet($sheet);
        } finally {
            $book->disconnectWorksheets();
        }
    }

    public static function validateSheet(Worksheet $sheet): array
    {
        $source = [];
        foreach (self::REGIONS as $code => [$startColumn, $startIndex]) {
            $source[$code] = [];
            $heading = strtoupper(trim((string) $sheet->getCell("{$startColumn}6")->getValue()));
            if (str_replace(' ', '_', $heading) !== $code) {
                throw new RuntimeException("Header {$startColumn}6 tidak sesuai wilayah {$code}.");
            }
            if (self::normalize((string) $sheet->getCell(self::column($startIndex + 2).'7')->getValue()) !== 'PEMILIKANPENGELOLA') {
                throw new RuntimeException("Header pemilikan/pengelola wilayah {$code} tidak sesuai.");
            }
            if (self::normalize((string) $sheet->getCell(self::column($startIndex + 1).'7')->getValue()) !== 'FASILITASKESEHATAN') {
                throw new RuntimeException("Header fasilitas wilayah {$code} tidak sesuai.");
            }
            foreach (array_keys(self::OWNERS) as $offset => $owner) {
                [$label] = self::OWNERS[$owner];
                $column = self::column($startIndex + 2 + $offset);
                if (self::normalize((string) $sheet->getCell("{$column}8")->getValue()) !== self::normalize($label)) {
                    throw new RuntimeException("Header {$column}8 tidak sesuai: {$label}.");
                }
            }

            foreach (self::SECTIONS as $row => [$sectionKey, $label]) {
                if (self::normalize((string) $sheet->getCell($startColumn.$row)->getValue()) !== self::normalize($label)) {
                    throw new RuntimeException("Bagian {$code} baris {$row} tidak sesuai: {$label}.");
                }
            }

            foreach (self::ROWS as $row => $definition) {
                [$key, $label] = $definition;
                $actual = self::normalize((string) $sheet->getCell(self::column($startIndex + 1).$row)->getValue());
                if ($actual !== self::normalize($definition[3] ?? $label)) {
                    throw new RuntimeException("Label fasilitas {$code} baris {$row} tidak sesuai: {$label}.");
                }
                foreach (array_keys(self::OWNERS) as $offset => $owner) {
                    $column = self::column($startIndex + 2 + $offset);
                    $cell = "{$column}{$row}";
                    $value = $sheet->getCell($cell)->getValue();
                    if ($sheet->getCell($cell)->getDataType() === 'f' || $value === null || $value === '' || (is_string($value) && trim($value) === '')) {
                        continue;
                    }
                    if (! is_numeric($value) || (float) $value < 0 || (int) $value != (float) $value) {
                        throw new RuntimeException("Nilai {$cell} harus bilangan bulat non-negatif atau kosong.");
                    }
                    $source[$code]["FASILITAS_{$key}_{$owner}"] = [(int) $value, $cell];
                }
            }
        }

        return $source;
    }

    public static function definitions(): array
    {
        $definitions = [];
        $position = 0;
        foreach (self::ROWS as $row => [$key, $label, $section]) {
            $rowNumber = $row === 15 ? '' : match (true) {
                $row < 13 => (string) ($row - 10),
                $row === 14 => '1',
                $row >= 16 && $row <= 18 => (string) ($row - 14),
                $row < 31 => (string) ($row - 19),
                default => (string) ($row - 31),
            };
            foreach (self::OWNERS as $owner => [, $ownerLabel]) {
                $code = "FASILITAS_{$key}_{$owner}";
                $definitions[$code] = [
                    'code' => $code,
                    'name' => $label.' — '.$ownerLabel,
                    'data_type' => 'numeric',
                    'value_kind' => 'base',
                    'categories' => ['row_key' => $key, 'row_number' => $rowNumber, 'facility' => $label, 'section' => $section, 'owner' => $owner, 'owner_label' => $ownerLabel],
                    'unit' => 'unit',
                    'decimal_places' => 0,
                    'is_required' => true,
                    'is_active' => true,
                ];
                $position++;
            }
            $totalCode = "FASILITAS_{$key}_TOTAL";
            $definitions[$totalCode] = [
                'code' => $totalCode,
                'name' => $label.' — Jumlah',
                'data_type' => 'numeric',
                'value_kind' => 'derived',
                'formula' => ['op' => 'add', 'args' => array_map(fn (string $owner) => "FASILITAS_{$key}_{$owner}", array_keys(self::OWNERS))],
                'categories' => ['row_key' => $key, 'row_number' => $rowNumber, 'facility' => $label, 'section' => $section, 'owner' => 'TOTAL', 'owner_label' => 'Jumlah'],
                'unit' => 'unit',
                'decimal_places' => 0,
                'is_required' => false,
                'is_active' => true,
            ];
            $position++;
        }

        return $definitions;
    }

    private static function normalize(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', strtoupper(preg_replace('/\s+/u', ' ', trim($value))));
    }

    private static function column(int $index): string
    {
        $column = '';
        while ($index > 0) {
            $index--;
            $column = chr($index % 26 + 65).$column;
            $index = intdiv($index, 26);
        }

        return $column;
    }
}
