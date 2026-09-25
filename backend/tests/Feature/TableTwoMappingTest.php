<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\IndicatorValueRevision;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\Submission;
use App\Models\User;
use App\Services\IndicatorCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TableTwoMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_two_maps_all_age_sex_values_and_calculates_summaries(): void
    {
        $this->seed();
        $this->artisan('profile:import-catalog', [
            'workbook' => base_path('../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx'), '--year' => 2024,
        ])->assertSuccessful();
        $this->artisan('profile:map-table-two', ['--year' => 2024])->assertSuccessful();

        $table = ReportingTable::where('code', 'T02')->with('indicators')->firstOrFail();
        $this->assertSame('ready', $table->mapping_status);
        $this->assertCount(69, $table->indicators);
        $this->assertCount(32, $table->indicators->where('value_kind', 'base'));
        $this->assertCount(37, $table->indicators->where('value_kind', 'derived'));
        $this->assertSame(['kelompok_umur' => '0 - 4', 'jenis_kelamin' => 'Laki-laki'], $table->indicators->firstWhere('code', 'PENDUDUK_0_4_L')->categories);
        $this->assertSame(['kelompok_umur' => '75+', 'jenis_kelamin' => 'Perempuan'], $table->indicators->firstWhere('code', 'PENDUDUK_75_PLUS_P')->categories);
        $this->assertDatabaseCount('indicator_value_revisions', 224);

        foreach (['PANGKALPINANG' => [9788, 9301, 242285, 102.4, 48.67], 'BELITUNG_TIMUR' => [5445, 5107, 133386, 105.4, 45.70]] as $regionCode => [$male, $female, $total, $ratio, $dependency]) {
            $region = Region::where('code', $regionCode)->firstOrFail();
            $submission = Submission::where('reporting_table_id', $table->id)->where('region_id', $region->id)->with('values')->firstOrFail();
            $values = $submission->values->keyBy('indicator_id');
            $this->assertSame($male, (int) $values[$table->indicators->firstWhere('code', 'PENDUDUK_0_4_L')->id]->numeric_value);
            $this->assertSame($female, (int) $values[$table->indicators->firstWhere('code', 'PENDUDUK_0_4_P')->id]->numeric_value);
            $calculated = app(IndicatorCalculator::class)->calculate($table->indicators, $submission->values);
            $this->assertSame((float) $total, $calculated[$table->indicators->firstWhere('code', 'PENDUDUK_TOTAL')->id]);
            $this->assertSame($ratio, $calculated[$table->indicators->firstWhere('code', 'PENDUDUK_TOTAL_RASIO')->id]);
            $this->assertSame($dependency, $calculated[$table->indicators->firstWhere('code', 'ANGKA_BEBAN_TANGGUNGAN')->id]);
        }
    }

    public function test_existing_values_and_locked_submissions_survive_reruns(): void
    {
        $this->seed();
        $this->artisan('profile:import-catalog', [
            'workbook' => base_path('../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx'), '--year' => 2024,
        ])->assertSuccessful();
        $table = ReportingTable::where('code', 'T02')->firstOrFail();
        $oldIndicator = Indicator::create(['reporting_table_id' => $table->id, 'code' => 'WRONG', 'name' => 'Header Salah', 'data_type' => 'numeric']);
        $region = Region::where('code', 'PANGKALPINANG')->firstOrFail();
        $submission = Submission::create(['reporting_table_id' => $table->id, 'reporting_year_id' => $table->reporting_year_id, 'region_id' => $region->id, 'status' => 'not_started', 'version' => 3]);
        IndicatorValue::create(['submission_id' => $submission->id, 'indicator_id' => $oldIndicator->id, 'numeric_value' => 7]);

        $this->artisan('profile:map-table-two', ['--year' => 2024])->assertSuccessful();
        $indicator = Indicator::where('reporting_table_id', $table->id)->where('code', 'PENDUDUK_0_4_L')->firstOrFail();
        $value = IndicatorValue::where('submission_id', $submission->id)->where('indicator_id', $indicator->id)->firstOrFail();
        $value->update(['numeric_value' => 42]);
        $other = Submission::where('reporting_table_id', $table->id)->where('region_id', '!=', $region->id)->firstOrFail();
        $other->update(['status' => 'verified', 'version' => 8]);
        IndicatorValue::where('submission_id', $other->id)->delete();
        $revisionCount = IndicatorValueRevision::count();

        $this->artisan('profile:map-table-two', ['--year' => 2024])->assertSuccessful();

        $this->assertSame(42, (int) $value->fresh()->numeric_value);
        $this->assertSame(3, $submission->fresh()->version);
        $this->assertSame('not_started', $submission->fresh()->status);
        $this->assertSame('verified', $other->fresh()->status);
        $this->assertSame(8, $other->fresh()->version);
        $this->assertDatabaseMissing('indicator_values', ['submission_id' => $other->id]);
        $this->assertSame($revisionCount, IndicatorValueRevision::count());
        $this->assertFalse($oldIndicator->fresh()->is_active);
        $this->assertDatabaseHas('indicator_values', ['id' => $value->id, 'numeric_value' => 42]);
    }

    public function test_generic_header_mapping_cannot_replace_table_two(): void
    {
        $this->seed();
        $table = ReportingTable::where('code', 'T02')->firstOrFail();
        Sanctum::actingAs(User::where('role', 'administrator')->firstOrFail());

        $this->putJson("/api/reporting-tables/{$table->id}/indicator-mapping", [
            'indicators' => [['code' => 'WRONG', 'name' => 'Header', 'data_type' => 'numeric', 'is_required' => true]],
        ])->assertStatus(409);
    }

    public function test_province_aggregates_current_region_values_only_when_every_region_has_data(): void
    {
        $this->seed();
        $table = ReportingTable::where('code', 'T02')->firstOrFail();
        $path = "/api/reporting-tables/{$table->id}/province";
        Sanctum::actingAs(User::where('role', 'administrator')->firstOrFail());
        $otherTable = ReportingTable::where('code', 'T01')->firstOrFail();
        $this->getJson("/api/reporting-tables/{$otherTable->id}/province")->assertNotFound();
        $table->update(['mapping_status' => 'pending']);
        $this->getJson($path)->assertNotFound();
        $this->artisan('profile:import-catalog', [
            'workbook' => base_path('../PROFIL-KES_2024_FINAL(hasilperbaikan).xlsx'), '--year' => 2024,
        ])->assertSuccessful();
        $this->artisan('profile:map-table-two', ['--year' => 2024])->assertSuccessful();
        $table->load('indicators');
        $id = fn (string $code) => (string) $table->indicators->firstWhere('code', $code)->id;
        $baseCount = IndicatorValue::count();

        $this->getJson($path)->assertOk()
            ->assertJsonPath('complete_base_count', 32)
            ->assertJsonPath('region_count', 7)
            ->assertJsonPath('calculated_values.'.$id('PENDUDUK_TOTAL_L'), 790861)
            ->assertJsonPath('calculated_values.'.$id('PENDUDUK_TOTAL_P'), 751742)
            ->assertJsonPath('calculated_values.'.$id('PENDUDUK_TOTAL'), 1542603)
            ->assertJsonPath('calculated_values.'.$id('PENDUDUK_TOTAL_RASIO'), 105.2)
            ->assertJsonPath('calculated_values.'.$id('ANGKA_BEBAN_TANGGUNGAN'), 45.10);
        $this->assertSame($baseCount, IndicatorValue::count());

        $indicator = $table->indicators->firstWhere('code', 'PENDUDUK_0_4_L');
        $value = IndicatorValue::where('indicator_id', $indicator->id)->firstOrFail();
        $original = (float) $value->numeric_value;
        $before = $this->getJson($path)->json('values.'.$id('PENDUDUK_0_4_L'));
        $value->update(['numeric_value' => 0]);
        $this->getJson($path)->assertOk()
            ->assertJsonPath('complete_base_count', 32)
            ->assertJsonPath('values.'.$id('PENDUDUK_0_4_L'), (int) ($before - $original));
        $value->update(['numeric_value' => null]);
        $this->getJson($path)->assertOk()
            ->assertJsonPath('complete_base_count', 31)
            ->assertJsonMissingPath('values.'.$id('PENDUDUK_0_4_L'))
            ->assertJsonPath('calculated_values.'.$id('PENDUDUK_0_4_TOTAL'), null)
            ->assertJsonPath('calculated_values.'.$id('PENDUDUK_TOTAL'), null)
            ->assertJsonPath('calculated_values.'.$id('ANGKA_BEBAN_TANGGUNGAN'), null);
        $value->update(['not_applicable' => true, 'numeric_value' => 20]);
        $this->getJson($path)->assertOk()->assertJsonPath('complete_base_count', 31);

        $value->update(['not_applicable' => false, 'numeric_value' => 0]);
        $female = $table->indicators->firstWhere('code', 'PENDUDUK_0_4_P');
        IndicatorValue::where('indicator_id', $female->id)->update(['numeric_value' => 0]);
        $this->getJson($path)->assertOk()
            ->assertJsonPath('complete_base_count', 32)
            ->assertJsonPath('calculated_values.'.$id('PENDUDUK_0_4_RASIO'), null);

        $value->delete();
        $this->getJson($path)->assertOk()->assertJsonPath('complete_base_count', 31);

        Sanctum::actingAs(User::where('role', 'operator')->firstOrFail());
        $this->getJson($path)->assertForbidden();
    }
}
