<?php

namespace App\Console\Commands;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\IndicatorValueRevision;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MapTableOne extends Command
{
    protected $signature = 'profile:map-table-one {--year=2024} {--without-values : Jangan masukkan Nilai Indikator dari workbook}';

    protected $description = 'Petakan Indikator resmi Tabel Pelaporan 1';

    public function handle(): int
    {
        $table = ReportingTable::where('code', 'T01')
            ->whereHas('reportingYear', fn ($query) => $query->where('year', (int) $this->option('year')))
            ->firstOrFail();

        $definitions = [
            ['code' => 'LUAS_WILAYAH', 'name' => 'Luas Wilayah', 'unit' => 'km²', 'value_kind' => 'base', 'decimal_places' => 2],
            ['code' => 'JUMLAH_DESA', 'name' => 'Jumlah Desa', 'unit' => 'desa', 'value_kind' => 'base'],
            ['code' => 'JUMLAH_KELURAHAN', 'name' => 'Jumlah Kelurahan', 'unit' => 'kelurahan', 'value_kind' => 'base'],
            ['code' => 'JUMLAH_DESA_KELURAHAN', 'name' => 'Jumlah Desa dan Kelurahan', 'unit' => 'wilayah', 'value_kind' => 'derived', 'formula' => ['op' => 'add', 'args' => ['JUMLAH_DESA', 'JUMLAH_KELURAHAN']]],
            ['code' => 'JUMLAH_PENDUDUK', 'name' => 'Jumlah Penduduk', 'unit' => 'jiwa', 'value_kind' => 'base'],
            ['code' => 'JUMLAH_RUMAH_TANGGA', 'name' => 'Jumlah Rumah Tangga', 'unit' => 'rumah tangga', 'value_kind' => 'base'],
            ['code' => 'RATA_RATA_JIWA_RUMAH_TANGGA', 'name' => 'Rata-rata Jiwa per Rumah Tangga', 'unit' => 'jiwa/rumah tangga', 'value_kind' => 'derived', 'decimal_places' => 2, 'formula' => ['op' => 'divide', 'args' => ['JUMLAH_PENDUDUK', 'JUMLAH_RUMAH_TANGGA']]],
            ['code' => 'KEPADATAN_PENDUDUK', 'name' => 'Kepadatan Penduduk', 'unit' => 'jiwa/km²', 'value_kind' => 'derived', 'decimal_places' => 2, 'formula' => ['op' => 'divide', 'args' => ['JUMLAH_PENDUDUK', 'LUAS_WILAYAH']]],
        ];
        $workbookValues = [
            'BANGKA' => [2950.9, 62, 19, 337755, 108234],
            'BELITUNG' => [2293.61, 42, 7, 189945, 46398],
            'BANGKA_BARAT' => [2862.6, 60, 6, 216238, 69065],
            'BANGKA_TENGAH' => [2155.1, 56, 7, 209117, 66171],
            'BANGKA_SELATAN' => [3607.08, 50, 3, 213877, 69462],
            'BELITUNG_TIMUR' => [2506.9, 39, 0, 133386, 41756],
            'PANGKALPINANG' => [104.5, 0, 42, 242285, 75989],
        ];
        $baseCodes = ['LUAS_WILAYAH', 'JUMLAH_DESA', 'JUMLAH_KELURAHAN', 'JUMLAH_PENDUDUK', 'JUMLAH_RUMAH_TANGGA'];

        DB::transaction(function () use ($table, $definitions, $workbookValues, $baseCodes) {
            $table->allIndicators()->update(['is_active' => false]);
            foreach ($definitions as $position => $definition) {
                Indicator::updateOrCreate(
                    ['reporting_table_id' => $table->id, 'code' => $definition['code']],
                    [
                        ...$definition,
                        'data_type' => 'numeric',
                        'is_required' => $definition['value_kind'] === 'base',
                        'is_active' => true,
                        'position' => $position + 1,
                    ],
                );
            }
            $table->update(['mapping_status' => 'ready']);

            if ($this->option('without-values')) {
                return;
            }

            foreach ($table->submissions as $submission) {
                $fromStatus = $submission->status;
                $submission->update([
                    'status' => 'not_started',
                    'version' => $submission->version + 1,
                    'completed_at' => null,
                    'completed_by' => null,
                    'verified_at' => null,
                    'verified_by' => null,
                ]);
                SubmissionEvent::create([
                    'submission_id' => $submission->id,
                    'user_id' => null,
                    'action' => 'catalog_remap',
                    'from_status' => $fromStatus,
                    'to_status' => 'not_started',
                    'reason' => 'Definisi Tabel Pelaporan 1 dipetakan ulang dari workbook resmi.',
                ]);
            }

            $indicatorIds = $table->indicators()->whereIn('code', $baseCodes)->pluck('id', 'code');
            foreach ($workbookValues as $regionCode => $numbers) {
                $region = Region::where('code', $regionCode)->firstOrFail();
                $submission = Submission::firstOrCreate([
                    'region_id' => $region->id,
                    'reporting_year_id' => $table->reporting_year_id,
                    'reporting_table_id' => $table->id,
                ], ['status' => 'not_started', 'version' => 0]);
                foreach (array_combine($baseCodes, $numbers) as $code => $number) {
                    $value = IndicatorValue::firstOrCreate([
                        'submission_id' => $submission->id,
                        'indicator_id' => $indicatorIds[$code],
                    ], ['numeric_value' => $number]);
                    if ($value->wasRecentlyCreated) {
                        IndicatorValueRevision::create([
                            'indicator_value_id' => $value->id,
                            'user_id' => null,
                            'old_value' => null,
                            'new_value' => ['numeric_value' => $number],
                            'reason' => 'Nilai awal diimpor dari Sheet 1 workbook Profil Kesehatan 2024.',
                        ]);
                    }
                }
            }
        });

        $this->info('Tabel Pelaporan 1 siap digunakan dengan 5 Nilai Dasar dan 3 Nilai Turunan.');

        return self::SUCCESS;
    }
}
