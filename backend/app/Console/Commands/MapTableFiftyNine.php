<?php

namespace App\Console\Commands;

use App\Models\Indicator;
use App\Models\ReportingTable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MapTableFiftyNine extends Command
{
    protected $signature = 'profile:map-table-59 {--year=2024}';

    protected $description = 'Petakan kelompok umur dan jenis kelamin Tabel Pelaporan 59';

    public function handle(): int
    {
        $table = ReportingTable::where('code', 'T59')
            ->whereHas('reportingYear', fn ($query) => $query->where('year', (int) $this->option('year')))
            ->firstOrFail();
        $ageGroups = [
            'LE4' => '≤ 4 Tahun',
            '5_14' => '5 - 14 Tahun',
            '15_19' => '15 - 19 Tahun',
            '20_24' => '20 - 24 Tahun',
            '25_49' => '25 - 49 Tahun',
            'GE50' => '≥ 50 Tahun',
        ];

        DB::transaction(function () use ($table, $ageGroups) {
            $table->allIndicators()->update(['is_active' => false]);
            $position = 1;
            $totalCodes = [];
            $maleCodes = [];
            $femaleCodes = [];

            foreach ($ageGroups as $key => $label) {
                $male = "HIV_{$key}_L";
                $female = "HIV_{$key}_P";
                $total = "HIV_{$key}_TOTAL";
                $maleCodes[] = $male;
                $femaleCodes[] = $female;
                $totalCodes[] = $total;
                $this->indicator($table, $position++, $male, 'Kasus HIV Laki-laki', 'base', ['kelompok_umur' => $label, 'jenis_kelamin' => 'Laki-laki']);
                $this->indicator($table, $position++, $female, 'Kasus HIV Perempuan', 'base', ['kelompok_umur' => $label, 'jenis_kelamin' => 'Perempuan']);
                $this->indicator($table, $position++, $total, 'Jumlah Kasus HIV', 'derived', ['kelompok_umur' => $label, 'jenis_kelamin' => 'L+P'], ['op' => 'add', 'args' => [$male, $female]]);
            }

            $this->indicator($table, $position++, 'HIV_TOTAL_L', 'Jumlah Kasus HIV Laki-laki', 'derived', ['kelompok_umur' => 'Semua Umur', 'jenis_kelamin' => 'Laki-laki'], ['op' => 'add', 'args' => $maleCodes]);
            $this->indicator($table, $position++, 'HIV_TOTAL_P', 'Jumlah Kasus HIV Perempuan', 'derived', ['kelompok_umur' => 'Semua Umur', 'jenis_kelamin' => 'Perempuan'], ['op' => 'add', 'args' => $femaleCodes]);
            $this->indicator($table, $position++, 'HIV_TOTAL', 'Jumlah Kasus HIV', 'derived', ['kelompok_umur' => 'Semua Umur', 'jenis_kelamin' => 'L+P'], ['op' => 'add', 'args' => $totalCodes]);

            foreach ($ageGroups as $key => $label) {
                $this->indicator(
                    $table,
                    $position++,
                    "HIV_{$key}_PROPORSI",
                    'Proporsi Kelompok Umur',
                    'derived',
                    ['kelompok_umur' => $label, 'jenis_kelamin' => 'L+P'],
                    ['op' => 'percent', 'args' => ["HIV_{$key}_TOTAL", 'HIV_TOTAL']],
                    '%',
                    2,
                );
            }

            $table->update(['mapping_status' => 'ready']);
        });

        $this->info('Tabel Pelaporan 59 siap dengan 12 Nilai Dasar dan 15 Nilai Turunan.');

        return self::SUCCESS;
    }

    private function indicator(
        ReportingTable $table,
        int $position,
        string $code,
        string $name,
        string $kind,
        array $categories,
        ?array $formula = null,
        string $unit = 'kasus',
        int $decimalPlaces = 0,
    ): void {
        Indicator::updateOrCreate(
            ['reporting_table_id' => $table->id, 'code' => $code],
            [
                'name' => $name,
                'data_type' => 'numeric',
                'value_kind' => $kind,
                'formula' => $formula,
                'categories' => $categories,
                'unit' => $unit,
                'decimal_places' => $decimalPlaces,
                'is_required' => $kind === 'base',
                'is_active' => true,
                'position' => $position,
            ],
        );
    }
}
