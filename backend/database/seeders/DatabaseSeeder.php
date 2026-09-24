<?php

namespace Database\Seeders;

use App\Models\Indicator;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $regions = collect([
            ['code' => 'BANGKA', 'name' => 'Kabupaten Bangka'],
            ['code' => 'BELITUNG', 'name' => 'Kabupaten Belitung'],
            ['code' => 'BANGKA_BARAT', 'name' => 'Kabupaten Bangka Barat'],
            ['code' => 'BANGKA_TENGAH', 'name' => 'Kabupaten Bangka Tengah'],
            ['code' => 'BANGKA_SELATAN', 'name' => 'Kabupaten Bangka Selatan'],
            ['code' => 'BELITUNG_TIMUR', 'name' => 'Kabupaten Belitung Timur'],
            ['code' => 'PANGKALPINANG', 'name' => 'Kota Pangkalpinang'],
        ])->map(fn (array $region) => Region::updateOrCreate(['code' => $region['code']], $region));

        User::updateOrCreate(['email' => 'admin@example.com'], [
            'name' => 'Administrator Sistem',
            'password' => Hash::make('password'),
            'role' => 'administrator',
            'region_id' => null,
        ]);
        User::updateOrCreate(['email' => 'operator.bangka@example.com'], [
            'name' => 'Operator Kabupaten Bangka',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'region_id' => $regions->firstWhere('code', 'BANGKA')->id,
        ]);

        ReportingYear::updateOrCreate(['year' => 2023], ['status' => 'closed']);
        $year = ReportingYear::updateOrCreate(['year' => 2024], ['status' => 'open']);

        $tables = [
            [
                'code' => 'T01',
                'name' => 'Kependudukan dan Administrasi',
                'description' => 'Data dasar wilayah dan penanggung jawab Profil Kesehatan.',
                'indicators' => [
                    ['code' => 'JUMLAH_PENDUDUK', 'name' => 'Jumlah Penduduk', 'data_type' => 'numeric', 'unit' => 'jiwa'],
                    ['code' => 'NAMA_PENANGGUNG_JAWAB', 'name' => 'Nama Penanggung Jawab', 'data_type' => 'text'],
                    ['code' => 'TANGGAL_PEMUTAKHIRAN', 'name' => 'Tanggal Pemutakhiran', 'data_type' => 'date'],
                ],
            ],
            [
                'code' => 'T02',
                'name' => 'Fasilitas Kesehatan',
                'description' => 'Ringkasan ketersediaan fasilitas pelayanan kesehatan.',
                'indicators' => [
                    ['code' => 'JUMLAH_PUSKESMAS', 'name' => 'Jumlah Puskesmas', 'data_type' => 'numeric', 'unit' => 'unit'],
                    ['code' => 'JUMLAH_RUMAH_SAKIT', 'name' => 'Jumlah Rumah Sakit', 'data_type' => 'numeric', 'unit' => 'unit'],
                ],
            ],
            [
                'code' => 'T03',
                'name' => 'Kesehatan Ibu dan Anak',
                'description' => 'Indikator ringkas pelayanan kesehatan ibu dan anak.',
                'indicators' => [
                    ['code' => 'PERSALINAN_NAKES', 'name' => 'Persalinan Ditolong Tenaga Kesehatan', 'data_type' => 'numeric', 'unit' => 'orang'],
                    ['code' => 'CATATAN_KIA', 'name' => 'Catatan Kesehatan Ibu dan Anak', 'data_type' => 'text', 'is_required' => false],
                ],
            ],
        ];

        foreach ($tables as $tablePosition => $tableData) {
            $indicators = $tableData['indicators'];
            unset($tableData['indicators']);
            $table = ReportingTable::updateOrCreate(
                ['reporting_year_id' => $year->id, 'code' => $tableData['code']],
                [...$tableData, 'position' => $tablePosition + 1],
            );

            foreach ($indicators as $indicatorPosition => $indicator) {
                Indicator::updateOrCreate(
                    ['reporting_table_id' => $table->id, 'code' => $indicator['code']],
                    [...$indicator, 'is_required' => $indicator['is_required'] ?? true, 'position' => $indicatorPosition + 1],
                );
            }
        }

        $this->command?->info('Demo administrator: admin@example.com / password');
        $this->command?->info('Demo operator: operator.bangka@example.com / password');
    }
}
