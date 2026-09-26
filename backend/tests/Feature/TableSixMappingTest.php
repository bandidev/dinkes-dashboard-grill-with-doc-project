<?php

namespace Tests\Feature;

use App\Console\Commands\MapTableSix;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\Submission;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TableSixMappingTest extends TestCase
{
    use RefreshDatabase;

    private const REGIONS = [
        'BANGKA' => 'Kabupaten Bangka',
        'BELITUNG' => 'Kabupaten Belitung',
        'BANGKA_BARAT' => 'Kabupaten Bangka Barat',
        'BANGKA_TENGAH' => 'Kabupaten Bangka Tengah',
        'BANGKA_SELATAN' => 'Kabupaten Bangka Selatan',
        'BELITUNG_TIMUR' => 'Kabupaten Belitung Timur',
        'PANGKALPINANG' => 'Kota Pangkalpinang',
    ];

    public function test_mapper_imports_sheet_six_literals_and_preserves_operator_values_on_rerun(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T06',
            'name' => 'Kemampuan Gadar Level I',
            'source_sheet' => '6',
            'mapping_status' => 'pending',
        ]);
        foreach (self::REGIONS as $code => $name) {
            Region::create(['code' => $code, 'name' => $name]);
        }

        $this->artisan('profile:map-table-six', ['--year' => 2024])->assertSuccessful();

        $table->refresh()->load('indicators');
        $this->assertSame('ready', $table->mapping_status);
        $this->assertCount(4, $table->indicators->where('value_kind', 'base'));
        $this->assertCount(5, $table->indicators->where('value_kind', 'derived'));
        $this->assertSame(7, Submission::where('reporting_table_id', $table->id)->count());
        $this->assertSame(28, IndicatorValue::whereIn('submission_id', Submission::where('reporting_table_id', $table->id)->select('id'))->count());
        foreach (self::REGIONS as $code => $_) {
            $this->assertSame(4, Submission::where('reporting_table_id', $table->id)
                ->where('region_id', Region::where('code', $code)->value('id'))
                ->firstOrFail()->values()->count());
        }

        $provinceCity = Submission::where('reporting_table_id', $table->id)
            ->where('region_id', Region::where('code', 'PANGKALPINANG')->value('id'))
            ->firstOrFail();
        $pangkalpinangAble = $table->indicators->firstWhere('code', 'RS_UMUM_MAMPU_GADAR_LEVEL_I');
        $value = IndicatorValue::where('submission_id', $provinceCity->id)
            ->where('indicator_id', $pangkalpinangAble->id)
            ->firstOrFail();
        $this->assertSame(6, (int) $value->numeric_value); // literal AT10, never the incomplete Province formula.

        $value->update(['numeric_value' => 5]);
        $provinceCity->update(['version' => 3]);
        $revisionCount = $value->revisions()->count();

        $this->artisan('profile:map-table-six', ['--year' => 2024])->assertSuccessful();

        $this->assertSame(5, (int) $value->fresh()->numeric_value);
        $this->assertSame(3, $provinceCity->fresh()->version);
        $this->assertSame($revisionCount, $value->revisions()->count());

        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class])->assertSuccessful();
        $this->assertSame('ready', $table->fresh()->mapping_status);
        $this->assertSame(5, (int) $value->fresh()->numeric_value);
    }

    public function test_operators_must_complete_four_base_values_and_cannot_overreport_gadar(): void
    {
        [$year, $table, $bases] = $this->mappedTable();
        $region = Region::create(['code' => 'BANGKA', 'name' => 'Kabupaten Bangka']);
        Sanctum::actingAs(User::create([
            'name' => 'Operator Bangka',
            'email' => 'operator-t06@example.com',
            'password' => 'password',
            'role' => 'operator',
            'region_id' => $region->id,
        ]));

        $umumCount = $bases->firstWhere('code', 'RS_UMUM_JUMLAH');
        $umumAble = $bases->firstWhere('code', 'RS_UMUM_MAMPU_GADAR_LEVEL_I');
        $khususCount = $bases->firstWhere('code', 'RS_KHUSUS_JUMLAH');
        $khususAble = $bases->firstWhere('code', 'RS_KHUSUS_MAMPU_GADAR_LEVEL_I');

        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => [
                ['indicator_id' => $umumCount->id, 'value' => 2],
                ['indicator_id' => $umumAble->id, 'value' => 3],
            ],
        ])->assertUnprocessable();

        $draft = $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => [
                ['indicator_id' => $umumCount->id, 'value' => 3],
                ['indicator_id' => $umumAble->id, 'value' => 2],
                ['indicator_id' => $khususCount->id, 'value' => 0],
            ],
        ])->assertOk()->assertJsonPath('version', 1)->json();

        $this->postJson("/api/submissions/{$draft['id']}/complete", ['version' => 1])->assertUnprocessable();
        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 1,
            'values' => [['indicator_id' => $umumCount->id, 'value' => 1]],
        ])->assertUnprocessable();

        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 1,
            'values' => [['indicator_id' => $khususAble->id, 'value' => 0]],
        ])->assertOk()->assertJsonPath('version', 2)
            ->assertJsonPath('calculated_values.'.Indicator::where('reporting_table_id', $table->id)->where('code', 'RS_KHUSUS_PERSENTASE_GADAR_LEVEL_I')->value('id'), null)
            ->assertJsonPath('calculated_values.'.Indicator::where('reporting_table_id', $table->id)->where('code', 'RS_UMUM_PERSENTASE_GADAR_LEVEL_I')->value('id'), 66.67);

        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 2,
            'values' => [['indicator_id' => $umumCount->id, 'value' => 1.5]],
        ])->assertUnprocessable();

        $this->postJson("/api/submissions/{$draft['id']}/complete", ['version' => 2])
            ->assertOk()
            ->assertJsonPath('status', 'completed');
        $this->assertSame(4, Submission::findOrFail($draft['id'])->values()->count());
    }

    public function test_province_rekap_waits_for_all_seven_regions_then_includes_pangkalpinang(): void
    {
        [$year, $table, $bases] = $this->mappedTable();
        $submissions = [];
        foreach (array_keys(self::REGIONS) as $index => $code) {
            $region = Region::create(['code' => $code, 'name' => $code]);
            $submission = Submission::create([
                'region_id' => $region->id,
                'reporting_year_id' => $year->id,
                'reporting_table_id' => $table->id,
            ]);
            $submissions[$code] = $submission;
            foreach ($bases as $indicator) {
                if ($code === 'PANGKALPINANG' && $indicator->code === 'RS_UMUM_MAMPU_GADAR_LEVEL_I') {
                    continue;
                }
                $submission->values()->create([
                    'indicator_id' => $indicator->id,
                    'numeric_value' => match ($indicator->code) {
                        'RS_UMUM_JUMLAH' => $index + 1,
                        'RS_UMUM_MAMPU_GADAR_LEVEL_I' => $index,
                        'RS_KHUSUS_JUMLAH' => 0,
                        'RS_KHUSUS_MAMPU_GADAR_LEVEL_I' => 0,
                    },
                ]);
            }
        }
        Sanctum::actingAs(User::create([
            'name' => 'Administrator',
            'email' => 'admin-t06@example.com',
            'password' => 'password',
            'role' => 'administrator',
        ]));

        $this->putJson("/api/reporting-tables/{$table->id}/indicator-mapping", [
            'indicators' => [['code' => 'GENERIC', 'name' => 'Generic', 'data_type' => 'numeric', 'is_required' => true]],
        ])->assertStatus(409);

        $missing = $bases->firstWhere('code', 'RS_UMUM_MAMPU_GADAR_LEVEL_I');
        $this->getJson("/api/reporting-tables/{$table->id}/province")
            ->assertOk()
            ->assertJsonPath('complete_base_count', 3)
            ->assertJsonMissingPath('values.'.$missing->id);

        $submissions['PANGKALPINANG']->values()->create(['indicator_id' => $missing->id, 'numeric_value' => 6]);
        $this->getJson("/api/reporting-tables/{$table->id}/province")
            ->assertOk()
            ->assertJsonPath('region_count', 7)
            ->assertJsonPath('complete_base_count', 4)
            ->assertJsonPath('values.'.$bases->firstWhere('code', 'RS_UMUM_JUMLAH')->id, 28)
            ->assertJsonPath('values.'.$missing->id, 21)
            ->assertJsonPath('calculated_values.'.Indicator::where('reporting_table_id', $table->id)->where('code', 'RS_UMUM_PERSENTASE_GADAR_LEVEL_I')->value('id'), 75)
            ->assertJsonPath('calculated_values.'.Indicator::where('reporting_table_id', $table->id)->where('code', 'RS_KHUSUS_PERSENTASE_GADAR_LEVEL_I')->value('id'), null)
            ->assertJsonPath('calculated_values.'.Indicator::where('reporting_table_id', $table->id)->where('code', 'RS_TOTAL_JUMLAH')->value('id'), 28)
            ->assertJsonPath('calculated_values.'.Indicator::where('reporting_table_id', $table->id)->where('code', 'RS_TOTAL_MAMPU_GADAR_LEVEL_I')->value('id'), 21)
            ->assertJsonPath('calculated_values.'.Indicator::where('reporting_table_id', $table->id)->where('code', 'RS_TOTAL_PERSENTASE_GADAR_LEVEL_I')->value('id'), 75);
    }

    private function mappedTable(): array
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T06',
            'name' => 'Kemampuan Gadar Level I',
            'source_sheet' => '6',
            'mapping_status' => 'ready',
        ]);
        $indicators = collect(array_values(MapTableSix::definitions()))->map(fn (array $definition, int $position) => Indicator::create([
            ...$definition,
            'reporting_table_id' => $table->id,
            'position' => $position + 1,
        ]));

        return [$year, $table, $indicators->where('value_kind', 'base')->values()];
    }
}
