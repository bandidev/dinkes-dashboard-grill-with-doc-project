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
}
