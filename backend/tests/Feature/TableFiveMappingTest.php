<?php

namespace Tests\Feature;

use App\Console\Commands\MapTableFive;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\IndicatorValueRevision;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\Submission;
use App\Models\TableFiveProvinceDenominator;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TableFiveMappingTest extends TestCase
{
    use RefreshDatabase;

    public function test_mapper_imports_sheet_five_literals_and_preserves_operator_edits_on_rerun(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T05',
            'name' => 'Kunjungan Pasien menurut Fasilitas Kesehatan',
            'source_sheet' => '5',
            'mapping_status' => 'pending',
        ]);
        foreach ([
            'BANGKA' => 'Kabupaten Bangka', 'BELITUNG' => 'Kabupaten Belitung',
            'BANGKA_BARAT' => 'Kabupaten Bangka Barat', 'BANGKA_TENGAH' => 'Kabupaten Bangka Tengah',
            'BANGKA_SELATAN' => 'Kabupaten Bangka Selatan', 'BELITUNG_TIMUR' => 'Kabupaten Belitung Timur',
            'PANGKALPINANG' => 'Kota Pangkalpinang',
        ] as $code => $name) {
            Region::create(['code' => $code, 'name' => $name]);
        }

        $this->artisan('profile:map-table-five', ['--year' => 2024])->assertSuccessful();
        $table->refresh()->load('indicators');
        $this->assertSame('ready', $table->mapping_status);
        $this->assertCount(54, $table->indicators->where('value_kind', 'base'));
        $this->assertCount(54, $table->indicators->where('value_kind', 'derived'));

        $submission = Submission::where('reporting_table_id', $table->id)->where('region_id', Region::where('code', 'BANGKA')->value('id'))->firstOrFail();
        $indicator = $table->indicators->firstWhere('code', 'KUNJUNGAN_PUSKESMAS_RAWAT_JALAN_L');
        $value = IndicatorValue::where('submission_id', $submission->id)->where('indicator_id', $indicator->id)->firstOrFail();
        $this->assertNotNull($value->numeric_value);
        $this->assertSame(62894, (int) $value->numeric_value);
        $formulaCellIndicator = $table->indicators->firstWhere('code', 'KUNJUNGAN_PRAKTIK_MANDIRI_DOKTER_SPESIALIS_RAWAT_JALAN_L');
        $formulaCellSubmission = Submission::where('reporting_table_id', $table->id)->where('region_id', Region::where('code', 'BANGKA_TENGAH')->value('id'))->firstOrFail();
        $this->assertDatabaseMissing('indicator_values', [
            'submission_id' => $formulaCellSubmission->id,
            'indicator_id' => $formulaCellIndicator->id,
        ]);
        $denominators = TableFiveProvinceDenominator::where('reporting_year_id', $year->id)->firstOrFail();
        $this->assertSame(790861, $denominators->outpatient_l);
        $this->assertSame(751742, $denominators->outpatient_p);
        $this->assertSame(790861, $denominators->inpatient_l);
        $this->assertSame(751742, $denominators->inpatient_p);
        $oldNumericValue = $value->numeric_value;
        $value->update(['numeric_value' => 999]);
        IndicatorValueRevision::create([
            'indicator_value_id' => $value->id,
            'old_value' => ['numeric_value' => $oldNumericValue],
            'new_value' => ['numeric_value' => 999],
            'reason' => 'Nilai sampel operator.',
        ]);
        $submission->update(['version' => 3]);
        $revisionCount = IndicatorValueRevision::where('indicator_value_id', $value->id)->count();
        $denominatorRevisionCount = DB::table('table_five_province_denominator_revisions')->count();

        $this->artisan('profile:map-table-five', ['--year' => 2024])->assertSuccessful();

        $this->assertSame(999, (int) $value->fresh()->numeric_value);
        $this->assertSame(3, $submission->fresh()->version);
        $this->assertSame($revisionCount, IndicatorValueRevision::where('indicator_value_id', $value->id)->count());
        $this->assertSame($denominatorRevisionCount, DB::table('table_five_province_denominator_revisions')->count());
    }

    public function test_all_required_visit_values_accept_zero_and_allow_completion(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $region = Region::create(['code' => 'BANGKA', 'name' => 'Kabupaten Bangka']);
        $table = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T05',
            'name' => 'Kunjungan Pasien menurut Fasilitas Kesehatan',
            'source_sheet' => '5',
            'mapping_status' => 'ready',
        ]);
        foreach (array_values(MapTableFive::definitions()) as $position => $definition) {
            Indicator::create([...$definition, 'reporting_table_id' => $table->id, 'position' => $position + 1]);
        }
        Sanctum::actingAs(User::create([
            'name' => 'Operator Bangka',
            'email' => 'operator-t05@example.com',
            'password' => 'password',
            'role' => 'operator',
            'region_id' => $region->id,
        ]));

        $bases = $table->indicators()->where('value_kind', 'base')->get();
        $this->assertCount(54, $bases);
        $this->assertSame(54, $table->indicators()->where('value_kind', 'derived')->count());
        $this->putJson("/api/reporting-tables/{$table->id}/province-denominators", [
            'outpatient_l' => 1,
            'outpatient_p' => 1,
            'inpatient_l' => 1,
            'inpatient_p' => 1,
        ])->assertForbidden();
        $total = $table->indicators()->where('code', 'KUNJUNGAN_TOTAL_UTAMA_RAWAT_JALAN_LP')->firstOrFail();
        $draft = $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => $bases->map(fn (Indicator $indicator) => [
                'indicator_id' => $indicator->id,
                'value' => 0,
                'not_applicable' => false,
            ])->all(),
        ])->assertOk()
            ->assertJsonPath('status', 'not_started')
            ->assertJsonPath('version', 1)
            ->assertJsonPath('calculated_values.'.$total->id, 0)
            ->json();

        $this->postJson("/api/submissions/{$draft['id']}/complete", ['version' => 1])
            ->assertOk()
            ->assertJsonPath('status', 'completed');
        $this->assertSame(54, Submission::firstOrFail()->values()->count());
    }

    public function test_province_recalculates_complete_totals_and_coverage(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T05',
            'name' => 'Kunjungan Pasien menurut Fasilitas Kesehatan',
            'mapping_status' => 'ready',
        ]);
        $indicators = collect(array_values(MapTableFive::definitions()))->map(fn (array $definition, int $position) => Indicator::create([
            ...$definition,
            'reporting_table_id' => $table->id,
            'position' => $position + 1,
        ]));
        $bases = $indicators->where('value_kind', 'base');
        foreach (['BANGKA', 'BELITUNG', 'BANGKA_BARAT', 'BANGKA_TENGAH', 'BANGKA_SELATAN', 'BELITUNG_TIMUR', 'PANGKALPINANG'] as $index => $code) {
            $region = Region::create(['code' => $code, 'name' => $code]);
            $submission = Submission::create([
                'region_id' => $region->id,
                'reporting_year_id' => $year->id,
                'reporting_table_id' => $table->id,
            ]);
            foreach ($bases as $indicator) {
                $submission->values()->create([
                    'indicator_id' => $indicator->id,
                    'numeric_value' => $indicator->code === 'KUNJUNGAN_PUSKESMAS_RAWAT_JALAN_L' ? $index + 1 : 0,
                ]);
            }
        }
        Sanctum::actingAs(User::create([
            'name' => 'Admin',
            'email' => 'admin-t05@example.com',
            'password' => 'password',
            'role' => 'administrator',
        ]));

        $bangkaSubmission = Submission::where('reporting_table_id', $table->id)
            ->where('region_id', Region::where('code', 'BANGKA')->value('id'))
            ->firstOrFail();
        $outpatientMale = $bases->firstWhere('code', 'KUNJUNGAN_PUSKESMAS_RAWAT_JALAN_L');
        $missingValue = IndicatorValue::where('submission_id', $bangkaSubmission->id)
            ->where('indicator_id', $outpatientMale->id)
            ->firstOrFail();
        $missingValue->delete();

        $this->putJson("/api/reporting-tables/{$table->id}/province-denominators", [
            'outpatient_l' => 56,
            'outpatient_p' => 1,
            'inpatient_l' => 10,
            'inpatient_p' => 10,
        ])->assertCreated();

        $this->putJson("/api/reporting-tables/{$table->id}/province-denominators", [
            'outpatient_l' => -1,
            'outpatient_p' => 1,
            'inpatient_l' => 10,
            'inpatient_p' => 10,
        ])->assertUnprocessable();

        $this->getJson("/api/reporting-tables/{$table->id}/province")
            ->assertOk()
            ->assertJsonPath('complete_base_count', 53)
            ->assertJsonPath('coverage_values.outpatient_l', null);
        $bangkaSubmission->values()->create(['indicator_id' => $outpatientMale->id, 'numeric_value' => 1]);

        $this->getJson("/api/reporting-tables/{$table->id}/province")
            ->assertOk()
            ->assertJsonPath('region_count', 7)
            ->assertJsonPath('complete_base_count', 54)
            ->assertJsonPath('province_denominators.outpatient_total', 57)
            ->assertJsonPath('coverage_values.outpatient_l', 50)
            ->assertJsonPath('coverage_values.outpatient_total', 49.12);
    }
}
