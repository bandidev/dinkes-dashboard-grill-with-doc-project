<?php

namespace Tests\Feature;

use App\Console\Commands\MapTableThree;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\IndicatorValueRevision;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class TableThreeMappingTest extends TestCase
{
    use RefreshDatabase;

    private const REGIONS = [
        'BANGKA' => ['J', 'K', 'L', 'M'],
        'BELITUNG' => ['S', 'T', 'U', 'V'],
        'BANGKA_BARAT' => ['AB', 'AC', 'AD', 'AE'],
        'BANGKA_TENGAH' => ['AK', 'AL', 'AM', 'AN'],
        'BANGKA_SELATAN' => ['AT', 'AU', 'AV', 'AW'],
        'BELITUNG_TIMUR' => ['BC', 'BD', 'BE', 'BF'],
        'PANGKALPINANG' => ['BL', 'BM', 'BN', 'BO'],
    ];

    private const ROWS = [
        11 => 'PENDUDUK BERUMUR 15 TAHUN KE ATAS',
        12 => 'PENDUDUK BERUMUR 15 TAHUN KE ATAS YANG MELEK HURUF',
        14 => 'a, TIDAK MEMILIKI IJAZAH SD',
        15 => 'b, SD/MI',
        16 => 'c, SMP/MTs',
        17 => 'd, SMA/MA',
        18 => 'e, SEKOLAH MENENGAH KEJURUAN',
        19 => 'f, DIPLOMA I/DIPLOMA II',
        20 => 'g, AKADEMI/DIPLOMA III',
        21 => 'h, S1/DIPLOMA IV',
        22 => 'i, S2/S3 (MASTER/DOKTOR)',
    ];

    public function test_mapper_reads_only_numeric_sheet_three_values_and_preserves_formula_blanks(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T03',
            'name' => 'Penduduk berumur 15 tahun ke atas',
            'source_sheet' => '3',
            'mapping_status' => 'pending',
        ]);
        foreach (array_keys(self::REGIONS) as $code) {
            Region::create(['code' => $code, 'name' => $code]);
        }

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('3');
        foreach (self::REGIONS as $code => [$header, $variable, $male, $female]) {
            $sheet->setCellValue("{$header}7", str_replace('_', ' ', $code));
            foreach (self::ROWS as $row => $label) {
                $sheet->setCellValue("{$variable}{$row}", $label);
                $sheet->setCellValue("{$male}{$row}", 10 + $row);
                $sheet->setCellValue("{$female}{$row}", 20 + $row);
            }
        }
        $sheet->setCellValue('AD11', "=SUM('2'!X14:X26)");
        $sheet->setCellValue('AE11', "=SUM('2'!Y14:Y26)");
        $sheet->getCell('L12')->setValueExplicit(null, DataType::TYPE_NULL);
        $sheet->setCellValue('BN14', '0,00');
        $sheet->setCellValue('BO14', '0,00');
        $source = MapTableThree::validateSheet($sheet);
        $book->disconnectWorksheets();

        $this->assertCount(7, $source);
        $this->assertSame(21, $source['BANGKA']['PENDUDUK_15_PLUS_L'][0]);
        $this->assertArrayNotHasKey('MELEK_HURUF_L', $source['BANGKA']);
        $this->assertArrayNotHasKey('PENDUDUK_15_PLUS_L', $source['BANGKA_BARAT']);
        $this->assertArrayNotHasKey('TIDAK_MEMILIKI_IJAZAH_SD_L', $source['PANGKALPINANG']);

        $year->refresh();
        $bangkaBarat = Region::where('code', 'BANGKA_BARAT')->firstOrFail();
        $populationTable = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T02',
            'name' => 'Jumlah penduduk menurut umur',
            'mapping_status' => 'ready',
        ]);
        $populationIndicators = collect(['15_19', '20_24', '25_29', '30_34', '35_39', '40_44', '45_49', '50_54', '55_59', '60_64', '65_69', '70_74', '75_PLUS'])
            ->map(fn (string $age, int $position) => Indicator::create([
                'reporting_table_id' => $populationTable->id,
                'code' => "PENDUDUK_{$age}_L",
                'name' => $age,
                'data_type' => 'numeric',
                'value_kind' => 'base',
                'position' => $position + 1,
            ]));
        $populationSubmission = Submission::create([
            'region_id' => $bangkaBarat->id,
            'reporting_year_id' => $year->id,
            'reporting_table_id' => $populationTable->id,
        ]);
        foreach ($populationIndicators as $position => $indicator) {
            IndicatorValue::create([
                'submission_id' => $populationSubmission->id,
                'indicator_id' => $indicator->id,
                'numeric_value' => $position + 1,
            ]);
        }
        $mapper = new MapTableThree;
        $this->assertSame(91, $mapper->tableTwoPopulation($year, $bangkaBarat, 'L'));
        $this->assertNull($mapper->tableTwoPopulation($year, $bangkaBarat, 'P'));

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'table-three-'.uniqid('', true).'.xlsx';
        $sourceBook = new Spreadsheet;
        $sourceSheet = $sourceBook->getActiveSheet();
        $sourceSheet->setTitle('3');
        foreach (self::REGIONS as $code => [$header, $variable, $male, $female]) {
            $sourceSheet->setCellValue("{$header}7", str_replace('_', ' ', $code));
            foreach (self::ROWS as $row => $label) {
                $sourceSheet->setCellValue("{$variable}{$row}", $label);
                $sourceSheet->setCellValue("{$male}{$row}", 10 + $row);
                $sourceSheet->setCellValue("{$female}{$row}", 20 + $row);
            }
        }
        $sourceSheet->setCellValue('AD11', "=SUM('2'!X14:X26)");
        $sourceSheet->setCellValue('AE11', "=SUM('2'!Y14:Y26)");
        $sourceSheet->getCell('L12')->setValueExplicit(null, DataType::TYPE_NULL);
        $sourceSheet->setCellValue('BN14', '0,00');
        $sourceSheet->setCellValue('BO14', '0,00');
        (new Xlsx($sourceBook))->save($path);
        $sourceBook->disconnectWorksheets();
        try {
            $this->artisan('profile:map-table-three', ['--year' => 2024, '--workbook' => $path])->assertSuccessful();
            $table->refresh();
            $this->assertSame('ready', $table->mapping_status);
            $this->assertCount(63, $table->indicators);
            $this->assertCount(22, $table->indicators->where('value_kind', 'base'));
            $this->assertCount(41, $table->indicators->where('value_kind', 'derived'));
            $this->assertDatabaseCount('indicator_value_revisions', 150);
            $this->assertDatabaseHas('indicator_values', ['indicator_id' => $table->indicators->firstWhere('code', 'PENDUDUK_15_PLUS_L')->id, 'numeric_value' => 21]);

            $operator = User::create(['name' => 'Operator', 'email' => 'table-three-rerun@example.com', 'password' => 'password', 'role' => 'operator', 'region_id' => Region::where('code', 'BANGKA')->value('id')]);
            $operatorSubmission = Submission::where('reporting_table_id', $table->id)->where('region_id', $operator->region_id)->firstOrFail();
            $operatorValue = IndicatorValue::where('submission_id', $operatorSubmission->id)->where('indicator_id', $table->indicators->firstWhere('code', 'PENDUDUK_15_PLUS_L')->id)->firstOrFail();
            $operatorValue->update(['numeric_value' => 777]);
            $operatorSubmission->update(['version' => 4]);
            $locked = Submission::where('reporting_table_id', $table->id)->where('region_id', Region::where('code', 'BELITUNG')->value('id'))->firstOrFail();
            $locked->update(['status' => 'verified', 'version' => 7]);
            $revisionCount = IndicatorValueRevision::count();
            $this->artisan('profile:map-table-three', ['--year' => 2024, '--workbook' => $path])->assertSuccessful();
            $this->assertSame(777, (int) $operatorValue->fresh()->numeric_value);
            $this->assertSame(4, $operatorSubmission->fresh()->version);
            $this->assertSame('verified', $locked->fresh()->status);
            $this->assertSame(7, $locked->fresh()->version);
            $this->assertSame($revisionCount, IndicatorValueRevision::count());
            $this->seed();
            $this->assertSame(63, $table->indicators()->count());
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }

    public function test_mapper_rejects_invalid_sheet_three_region_header(): void
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('3');
        $sheet->setCellValue('J7', 'INVALID');

        $this->expectException(\RuntimeException::class);
        MapTableThree::validateSheet($sheet);
    }

    public function test_mapper_imports_bangka_barat_age_15_plus_from_complete_table_two_data(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        foreach (array_keys(self::REGIONS) as $code) {
            $regions[$code] = Region::create(['code' => $code, 'name' => $code]);
        }
        $region = $regions['BANGKA_BARAT'];
        $table = ReportingTable::create(['reporting_year_id' => $year->id, 'code' => 'T02', 'name' => 'Penduduk', 'mapping_status' => 'ready']);
        foreach (['15_19', '20_24', '25_29', '30_34', '35_39', '40_44', '45_49', '50_54', '55_59', '60_64', '65_69', '70_74', '75_PLUS'] as $position => $age) {
            $indicator = Indicator::create(['reporting_table_id' => $table->id, 'code' => "PENDUDUK_{$age}_L", 'name' => $age, 'data_type' => 'numeric', 'value_kind' => 'base', 'position' => $position + 1]);
            $submission ??= Submission::create(['region_id' => $region->id, 'reporting_year_id' => $year->id, 'reporting_table_id' => $table->id]);
            IndicatorValue::create(['submission_id' => $submission->id, 'indicator_id' => $indicator->id, 'numeric_value' => 1]);
        }
        ReportingTable::create(['reporting_year_id' => $year->id, 'code' => 'T03', 'name' => 'Pendidikan', 'source_sheet' => '3']);

        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle('3');
        foreach (self::REGIONS as $code => [$header, $variable, $male, $female]) {
            $sheet->setCellValue("{$header}7", str_replace('_', ' ', $code));
            foreach (self::ROWS as $row => $label) {
                $sheet->setCellValue("{$variable}{$row}", $label);
                $sheet->setCellValue("{$male}{$row}", 1);
                $sheet->setCellValue("{$female}{$row}", 1);
            }
        }
        $sheet->setCellValue('AD11', "=SUM('2'!X14:X26)");
        $sheet->setCellValue('AE11', "=SUM('2'!Y14:Y26)");
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'table-three-t02-'.uniqid('', true).'.xlsx';
        (new Xlsx($book))->save($path);
        $book->disconnectWorksheets();

        try {
            $this->artisan('profile:map-table-three', ['--year' => 2024, '--workbook' => $path])->assertSuccessful();
            $tableThree = ReportingTable::where('reporting_year_id', $year->id)->where('code', 'T03')->firstOrFail();
            $value = IndicatorValue::where('submission_id', Submission::where('reporting_table_id', $tableThree->id)->where('region_id', $region->id)->value('id'))
                ->where('indicator_id', $tableThree->indicators()->where('code', 'PENDUDUK_15_PLUS_L')->value('id'))->firstOrFail();
            $this->assertSame(13, (int) $value->numeric_value);
            $this->assertStringContainsString('Tabel 2', $value->revisions()->firstOrFail()->reason);
        } finally {
            unlink($path);
        }
    }

    public function test_province_sums_only_complete_rows_and_recalculates_percentages(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T03',
            'name' => 'Penduduk berumur 15 tahun ke atas',
            'mapping_status' => 'ready',
        ]);
        $definitions = [
            ['PENDUDUK_15_PLUS_L', 'base'], ['PENDUDUK_15_PLUS_P', 'base'],
            ['MELEK_HURUF_L', 'base'], ['MELEK_HURUF_P', 'base'],
            ['SD_MI_L', 'base'], ['SD_MI_P', 'base'],
            ['PENDUDUK_15_PLUS_TOTAL', 'derived', ['op' => 'add', 'args' => ['PENDUDUK_15_PLUS_L', 'PENDUDUK_15_PLUS_P']]],
            ['MELEK_HURUF_TOTAL', 'derived', ['op' => 'add', 'args' => ['MELEK_HURUF_L', 'MELEK_HURUF_P']]],
            ['MELEK_HURUF_PERCENT_L', 'derived', ['op' => 'percent', 'args' => ['MELEK_HURUF_L', 'PENDUDUK_15_PLUS_L']]],
            ['MELEK_HURUF_PERCENT_P', 'derived', ['op' => 'percent', 'args' => ['MELEK_HURUF_P', 'PENDUDUK_15_PLUS_P']]],
            ['MELEK_HURUF_PERCENT_TOTAL', 'derived', ['op' => 'percent', 'args' => ['MELEK_HURUF_TOTAL', 'PENDUDUK_15_PLUS_TOTAL']]],
            ['SD_MI_TOTAL', 'derived', ['op' => 'add', 'args' => ['SD_MI_L', 'SD_MI_P']]],
            ['SD_MI_PERCENT_L', 'derived', ['op' => 'percent', 'args' => ['SD_MI_L', 'PENDUDUK_15_PLUS_L']]],
        ];
        $indicators = collect($definitions)->mapWithKeys(function (array $definition, int $position) use ($table) {
            [$code, $kind] = $definition;
            $indicator = Indicator::create([
                'reporting_table_id' => $table->id,
                'code' => $code,
                'name' => $code,
                'data_type' => 'numeric',
                'value_kind' => $kind,
                'formula' => $definition[2] ?? null,
                'is_required' => $kind === 'base',
                'decimal_places' => str_contains($code, 'PERCENT') ? 2 : 0,
                'position' => $position + 1,
            ]);

            return [$code => $indicator];
        });
        $regions = collect([
            Region::create(['code' => 'TEST_A', 'name' => 'Kabupaten A']),
            Region::create(['code' => 'TEST_B', 'name' => 'Kabupaten B']),
        ]);
        $inputs = [
            'PENDUDUK_15_PLUS_L' => [100, 200], 'PENDUDUK_15_PLUS_P' => [100, 100],
            'MELEK_HURUF_L' => [75, 150], 'MELEK_HURUF_P' => [75, 90],
            'SD_MI_L' => [0, 10], 'SD_MI_P' => [20, 30],
        ];
        foreach ($regions as $index => $region) {
            $submission = Submission::create([
                'region_id' => $region->id,
                'reporting_year_id' => $year->id,
                'reporting_table_id' => $table->id,
            ]);
            foreach ($inputs as $code => $values) {
                IndicatorValue::create([
                    'submission_id' => $submission->id,
                    'indicator_id' => $indicators[$code]->id,
                    'numeric_value' => $values[$index],
                ]);
            }
        }
        $admin = User::create(['name' => 'Admin', 'email' => 'table-three-admin@example.com', 'password' => 'password', 'role' => 'administrator']);
        $operator = User::create(['name' => 'Operator', 'email' => 'table-three-operator@example.com', 'password' => 'password', 'role' => 'operator', 'region_id' => $regions->first()->id]);
        $path = "/api/reporting-tables/{$table->id}/province";
        Sanctum::actingAs($admin);
        $valueCount = IndicatorValue::count();
        $this->getJson($path)->assertOk()
            ->assertJsonPath('complete_base_count', 6)
            ->assertJsonPath('region_count', 2)
            ->assertJsonPath('values.'.$indicators['SD_MI_L']->id, 10)
            ->assertJsonPath('calculated_values.'.$indicators['MELEK_HURUF_PERCENT_L']->id, 75)
            ->assertJsonPath('calculated_values.'.$indicators['MELEK_HURUF_PERCENT_P']->id, 82.5)
            ->assertJsonPath('calculated_values.'.$indicators['MELEK_HURUF_PERCENT_TOTAL']->id, 78)
            ->assertJsonPath('calculated_values.'.$indicators['SD_MI_PERCENT_L']->id, 3.33);
        $this->assertSame($valueCount, IndicatorValue::count());

        IndicatorValue::whereIn('submission_id', Submission::where('reporting_table_id', $table->id)->pluck('id'))
            ->where('indicator_id', $indicators['PENDUDUK_15_PLUS_L']->id)->update(['numeric_value' => 0]);
        $this->getJson($path)->assertOk()
            ->assertJsonPath('complete_base_count', 6)
            ->assertJsonPath('calculated_values.'.$indicators['MELEK_HURUF_PERCENT_L']->id, null);
        $populationValues = IndicatorValue::whereIn('submission_id', Submission::where('reporting_table_id', $table->id)->pluck('id'))
            ->where('indicator_id', $indicators['PENDUDUK_15_PLUS_L']->id)->orderBy('id')->get();
        $populationValues[0]->update(['numeric_value' => 100]);
        $populationValues[1]->update(['numeric_value' => 200]);

        $missing = IndicatorValue::where('submission_id', Submission::where('region_id', $regions->last()->id)->value('id'))
            ->where('indicator_id', $indicators['SD_MI_P']->id)->firstOrFail();
        $missing->update(['numeric_value' => null]);
        $this->getJson($path)->assertOk()
            ->assertJsonPath('complete_base_count', 5)
            ->assertJsonMissingPath('values.'.$indicators['SD_MI_P']->id)
            ->assertJsonPath('calculated_values.'.$indicators['SD_MI_TOTAL']->id, null)
            ->assertJsonPath('calculated_values.'.$indicators['SD_MI_PERCENT_L']->id, 3.33);
        $missing->update(['numeric_value' => 30, 'not_applicable' => true]);
        $this->getJson($path)->assertOk()->assertJsonPath('complete_base_count', 5);

        Sanctum::actingAs($operator);
        $this->getJson($path)->assertForbidden();
    }

    public function test_t03_cannot_use_generic_indicator_mapping(): void
    {
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create(['reporting_year_id' => $year->id, 'code' => 'T03', 'name' => 'Tabel 3']);
        Sanctum::actingAs(User::create(['name' => 'Admin', 'email' => 'table-three-map-admin@example.com', 'password' => 'password', 'role' => 'administrator']));

        $this->putJson("/api/reporting-tables/{$table->id}/indicator-mapping", [
            'indicators' => [['code' => 'WRONG', 'name' => 'Header', 'data_type' => 'numeric', 'is_required' => true]],
        ])->assertStatus(409);
        $table->update(['mapping_status' => 'ready']);
        $this->putJson("/api/reporting-tables/{$table->id}/indicator-mapping", [
            'indicators' => [['code' => 'WRONG', 'name' => 'Header', 'data_type' => 'numeric', 'is_required' => true]],
        ])->assertStatus(409);
    }

    public function test_t03_draft_is_partial_numeric_and_requires_explicit_zero(): void
    {
        $region = Region::create(['code' => 'PARTIAL', 'name' => 'Kabupaten Partial']);
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create(['reporting_year_id' => $year->id, 'code' => 'T03', 'name' => 'Tabel 3', 'mapping_status' => 'ready']);
        $indicator = Indicator::create(['reporting_table_id' => $table->id, 'code' => 'PENDUDUK_15_PLUS_L', 'name' => 'Penduduk laki-laki 15 tahun ke atas', 'data_type' => 'numeric', 'value_kind' => 'base', 'is_required' => true]);
        $operator = User::create(['name' => 'Operator', 'email' => 'table-three-partial@example.com', 'password' => 'password', 'role' => 'operator', 'region_id' => $region->id]);
        Sanctum::actingAs($operator);

        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => [['indicator_id' => $indicator->id, 'value' => '']],
        ])->assertOk()->assertJsonPath('values.0.numeric_value', null);
        $submission = Submission::where('reporting_table_id', $table->id)->firstOrFail();
        $this->postJson('/api/submissions/'.$submission->id.'/complete', ['version' => 1])->assertUnprocessable();

        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 1,
            'values' => [['indicator_id' => $indicator->id, 'value' => 0]],
        ])->assertOk()->assertJsonPath('values.0.numeric_value', 0);
    }
}
