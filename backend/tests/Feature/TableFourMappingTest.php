<?php

namespace Tests\Feature;

use App\Console\Commands\MapTableFour;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TableFourMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_required_facility_values_accept_zero_and_allow_completion(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $region = Region::create(['code' => 'BANGKA', 'name' => 'Kabupaten Bangka']);
        $table = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T04',
            'name' => 'Fasilitas Kesehatan',
            'source_sheet' => '4',
            'mapping_status' => 'ready',
        ]);
        foreach (array_values(MapTableFour::definitions()) as $position => $definition) {
            Indicator::create([...$definition, 'reporting_table_id' => $table->id, 'position' => $position + 1]);
        }
        $operator = User::create([
            'name' => 'Operator Bangka',
            'email' => 'operator-t04@example.com',
            'password' => 'password',
            'role' => 'operator',
            'region_id' => $region->id,
        ]);
        Sanctum::actingAs($operator);

        $indicators = $table->indicators()->where('value_kind', 'base')->get();
        $this->assertCount(203, $indicators);
        $this->assertSame(29, $table->indicators()->where('value_kind', 'derived')->count());
        $firstTotal = $table->indicators()->where('code', 'FASILITAS_RUMAH_SAKIT_UMUM_TOTAL')->firstOrFail();
        $draft = $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => $indicators->map(fn (Indicator $indicator) => [
                'indicator_id' => $indicator->id,
                'value' => 0,
                'not_applicable' => false,
            ])->all(),
        ])->assertOk()
            ->assertJsonPath('status', 'not_started')
            ->assertJsonPath('version', 1)
            ->assertJsonPath('calculated_values.'.$firstTotal->id, 0)
            ->json();

        $this->postJson("/api/submissions/{$draft['id']}/complete", ['version' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'completed');
        $this->assertSame(203, Submission::firstOrFail()->values()->count());
    }

    public function test_province_recalculates_facility_totals_from_all_seven_regions(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create(['reporting_year_id' => $year->id, 'code' => 'T04', 'name' => 'Fasilitas', 'mapping_status' => 'ready']);
        $bases = collect(['KEMENKES', 'PEM_PROV', 'PEM_KAB_KOTA', 'TNI_POLRI', 'BUMN', 'SWASTA', 'ORGANISASI_KEMASYARAKATAN'])
            ->map(fn (string $owner, int $position) => Indicator::create([
                'reporting_table_id' => $table->id,
                'code' => "FASILITAS_RUMAH_SAKIT_UMUM_{$owner}",
                'name' => $owner,
                'data_type' => 'numeric',
                'value_kind' => 'base',
                'is_required' => true,
                'position' => $position + 1,
            ]));
        $total = Indicator::create([
            'reporting_table_id' => $table->id,
            'code' => 'FASILITAS_RUMAH_SAKIT_UMUM_TOTAL',
            'name' => 'Jumlah Rumah Sakit Umum',
            'data_type' => 'numeric',
            'value_kind' => 'derived',
            'formula' => ['op' => 'add', 'args' => $bases->pluck('code')->all()],
            'position' => 8,
        ]);
        foreach (['BANGKA', 'BELITUNG', 'BANGKA_BARAT', 'BANGKA_TENGAH', 'BANGKA_SELATAN', 'BELITUNG_TIMUR', 'PANGKALPINANG'] as $index => $code) {
            $region = Region::create(['code' => $code, 'name' => $code]);
            $submission = Submission::create(['region_id' => $region->id, 'reporting_year_id' => $year->id, 'reporting_table_id' => $table->id]);
            foreach ($bases as $ownerIndex => $indicator) {
                $submission->values()->create(['indicator_id' => $indicator->id, 'numeric_value' => $index === 0 && $ownerIndex === 0 ? 4 : 0]);
            }
        }
        $admin = User::create(['name' => 'Admin', 'email' => 'table-four-province@example.com', 'password' => 'password', 'role' => 'administrator']);
        Sanctum::actingAs($admin);

        $this->getJson("/api/reporting-tables/{$table->id}/province")
            ->assertOk()
            ->assertJsonPath('region_count', 7)
            ->assertJsonPath('complete_base_count', 7)
            ->assertJsonPath('calculated_values.'.$total->id, 4);
        $this->assertSame(49, IndicatorValue::count());
    }

    public function test_province_sums_available_facility_values_without_requiring_other_indicators(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create(['reporting_year_id' => $year->id, 'code' => 'T04', 'name' => 'Fasilitas', 'mapping_status' => 'ready']);
        $bases = collect(['KEMENKES', 'PEM_PROV', 'PEM_KAB_KOTA', 'TNI_POLRI', 'BUMN', 'SWASTA', 'ORGANISASI_KEMASYARAKATAN'])
            ->map(fn (string $owner, int $position) => Indicator::create([
                'reporting_table_id' => $table->id,
                'code' => "FASILITAS_RUMAH_SAKIT_UMUM_{$owner}",
                'name' => $owner,
                'data_type' => 'numeric',
                'value_kind' => 'base',
                'is_required' => true,
                'position' => $position + 1,
            ]));
        $total = Indicator::create([
            'reporting_table_id' => $table->id,
            'code' => 'FASILITAS_RUMAH_SAKIT_UMUM_TOTAL',
            'name' => 'Jumlah Rumah Sakit Umum',
            'data_type' => 'numeric',
            'value_kind' => 'derived',
            'formula' => ['op' => 'add', 'args' => $bases->pluck('code')->all()],
            'position' => 8,
        ]);
        foreach (['BANGKA', 'BELITUNG', 'BANGKA_BARAT', 'BANGKA_TENGAH', 'BANGKA_SELATAN', 'BELITUNG_TIMUR', 'PANGKALPINANG'] as $index => $code) {
            $region = Region::create(['code' => $code, 'name' => $code]);
            $submission = Submission::create(['region_id' => $region->id, 'reporting_year_id' => $year->id, 'reporting_table_id' => $table->id]);
            $submission->values()->create(['indicator_id' => $bases[0]->id, 'numeric_value' => $index + 1]);
        }
        Sanctum::actingAs(User::create(['name' => 'Admin', 'email' => 'partial-province@example.com', 'password' => 'password', 'role' => 'administrator']));

        $this->getJson("/api/reporting-tables/{$table->id}/province")
            ->assertOk()
            ->assertJsonPath('complete_base_count', 1)
            ->assertJsonPath('base_count', 7)
            ->assertJsonPath('values.'.$bases[0]->id, 28)
            ->assertJsonPath('calculated_values.'.$total->id, null);
    }
}
