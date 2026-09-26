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
        foreach ([
            'BANGKA' => ['Operator Kabupaten Bangka', 'operator.bangka@example.com'],
            'BELITUNG' => ['Operator Kabupaten Belitung', 'operator.belitung@example.com'],
            'BANGKA_BARAT' => ['Operator Kabupaten Bangka Barat', 'operator.bangka.barat@example.com'],
            'BANGKA_TENGAH' => ['Operator Kabupaten Bangka Tengah', 'operator.bangka.tengah@example.com'],
            'BANGKA_SELATAN' => ['Operator Kabupaten Bangka Selatan', 'operator.bangka.selatan@example.com'],
            'BELITUNG_TIMUR' => ['Operator Kabupaten Belitung Timur', 'operator.belitung.timur@example.com'],
            'PANGKALPINANG' => ['Operator Kota Pangkalpinang', 'operator.pangkalpinang@example.com'],
        ] as $regionCode => [$name, $email]) {
            User::updateOrCreate(['email' => $email], [
                'name' => $name,
                'password' => Hash::make('password'),
                'role' => 'operator',
                'region_id' => $regions->firstWhere('code', $regionCode)->id,
            ]);
        }

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
                'name' => 'Penduduk 15 Tahun ke Atas Menurut Melek Huruf dan Ijazah',
                'description' => 'Penduduk 15 tahun ke atas, melek huruf, dan ijazah tertinggi menurut jenis kelamin.',
                'mapping_status' => 'pending',
                'indicators' => [
                    ['code' => 'PERSALINAN_NAKES', 'name' => 'Persalinan Ditolong Tenaga Kesehatan', 'data_type' => 'numeric', 'unit' => 'orang'],
                    ['code' => 'CATATAN_KIA', 'name' => 'Catatan Kesehatan Ibu dan Anak', 'data_type' => 'text', 'is_required' => false],
                ],
            ],
            [
                'code' => 'T04',
                'name' => 'Jumlah Fasilitas Pelayanan Kesehatan Menurut Kepemilikan',
                'description' => 'Jumlah fasilitas kesehatan menurut jenis fasilitas dan pemilikan/pengelola.',
                'source_sheet' => '4',
                'mapping_status' => 'pending',
                'indicators' => [
                    ['code' => 'FASILITAS_PENDING', 'name' => 'Jalankan pemetaan khusus Sheet 4', 'data_type' => 'numeric', 'unit' => 'unit'],
                ],
            ],
            [
                'code' => 'T05',
                'name' => 'Jumlah Kunjungan Pasien menurut Fasilitas Kesehatan',
                'description' => 'Jumlah kunjungan rawat jalan, rawat inap, dan gangguan jiwa menurut jenis fasilitas dan jenis kelamin.',
                'source_sheet' => '5',
                'mapping_status' => 'pending',
                'indicators' => [
                    ['code' => 'KUNJUNGAN_PENDING', 'name' => 'Jalankan pemetaan khusus Sheet 5', 'data_type' => 'numeric', 'unit' => 'kunjungan'],
                ],
            ],
            [
                'code' => 'T06',
                'name' => 'Persentase Rumah Sakit dengan Kemampuan Pelayanan Gawat Darurat Level I',
                'description' => 'Jumlah rumah sakit umum dan khusus serta kemampuan pelayanan Gadar Level I.',
                'source_sheet' => '6',
                'mapping_status' => 'pending',
                'indicators' => [
                    ['code' => 'GADAR_PENDING', 'name' => 'Jalankan pemetaan khusus Sheet 6', 'data_type' => 'numeric', 'unit' => 'rumah sakit'],
                ],
            ],
        ];

        foreach ($tables as $tablePosition => $tableData) {
            $indicators = $tableData['indicators'];
            unset($tableData['indicators']);
            $table = ReportingTable::firstOrNew(['reporting_year_id' => $year->id, 'code' => $tableData['code']]);
            if (($tableData['code'] === 'T03' && $table->exists && $table->mapping_status === 'ready' && $table->indicators()->where('code', 'PENDUDUK_15_PLUS_L')->exists())
                || ($tableData['code'] === 'T04' && $table->exists && $table->mapping_status === 'ready' && $table->indicators()->where('code', 'FASILITAS_RUMAH_SAKIT_UMUM_KEMENKES')->exists())
                || ($tableData['code'] === 'T05' && $table->exists && $table->mapping_status === 'ready' && $table->indicators()->where('code', 'KUNJUNGAN_PUSKESMAS_RAWAT_JALAN_L')->exists())
                || ($tableData['code'] === 'T06' && $table->exists && $table->mapping_status === 'ready' && $table->indicators()->where('code', 'RS_UMUM_JUMLAH')->exists())) {
                continue;
            }
            $table->fill([...$tableData, 'position' => $tablePosition + 1])->save();

            foreach ($indicators as $indicatorPosition => $indicator) {
                Indicator::updateOrCreate(
                    ['reporting_table_id' => $table->id, 'code' => $indicator['code']],
                    [...$indicator, 'is_required' => $indicator['is_required'] ?? true, 'position' => $indicatorPosition + 1],
                );
            }
        }

        $this->command?->info('Demo administrator: admin@example.com / password');
        $this->command?->info('Demo operators: operator.<region>@example.com / password');
    }
}
