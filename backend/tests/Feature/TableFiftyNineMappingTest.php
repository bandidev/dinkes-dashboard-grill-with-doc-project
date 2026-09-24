<?php

namespace Tests\Feature;

use App\Models\ReportingTable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableFiftyNineMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_59_mapping_has_expected_categories_and_formula_counts(): void
    {
        $this->seed();
        $this->artisan('profile:import-catalog', [
            'workbook' => base_path('../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx'),
            '--year' => 2024,
        ])->assertSuccessful();
        $this->artisan('profile:map-table-59', ['--year' => 2024])->assertSuccessful();

        $table = ReportingTable::where('code', 'T59')->with('indicators')->firstOrFail();

        $this->assertSame('ready', $table->mapping_status);
        $this->assertCount(27, $table->indicators);
        $this->assertCount(12, $table->indicators->where('value_kind', 'base'));
        $this->assertCount(15, $table->indicators->where('value_kind', 'derived'));
        $this->assertSame(
            ['kelompok_umur' => '≤ 4 Tahun', 'jenis_kelamin' => 'Laki-laki'],
            $table->indicators->firstWhere('code', 'HIV_LE4_L')->categories,
        );
    }
}
