<?php

namespace Tests\Feature;

use App\Models\Indicator;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HealthProfileApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_a_hashed_sanctum_personal_access_token(): void
    {
        $user = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => 'secret1',
            'role' => 'administrator',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => $user->email,
            'password' => 'secret1',
            'device_name' => 'feature-test',
        ])->assertOk();

        [$id, $plainTextToken] = explode('|', $response->json('token'), 2);
        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $id,
            'token' => hash('sha256', $plainTextToken),
        ]);
        $this->assertDatabaseMissing('personal_access_tokens', ['token' => $plainTextToken]);
    }

    public function test_operator_cannot_access_another_regions_submission(): void
    {
        [$operatorA, $operatorB, $table, $indicator] = $this->scenario();
        Sanctum::actingAs($operatorA);

        $submission = $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => [['indicator_id' => $indicator->id, 'value' => 12]],
        ])->assertOk()->json();

        Sanctum::actingAs($operatorB);
        $this->getJson('/api/submissions/'.$submission['id'])->assertForbidden();
        $this->postJson('/api/submissions/'.$submission['id'].'/complete', [
            'version' => $submission['version'],
        ])->assertForbidden();
    }

    public function test_status_transitions_optimistic_locking_and_edit_locks(): void
    {
        [$operator, , $table, $indicator] = $this->scenario();
        $admin = User::create([
            'name' => 'Administrator',
            'email' => 'admin@example.com',
            'password' => 'secret1',
            'role' => 'administrator',
        ]);
        Sanctum::actingAs($operator);

        $draft = $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => [['indicator_id' => $indicator->id, 'value' => 12]],
        ])->assertOk()
            ->assertJsonPath('status', 'not_started')
            ->assertJsonPath('version', 1)
            ->json();

        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => [['indicator_id' => $indicator->id, 'value' => 13]],
        ])->assertStatus(409);

        $completed = $this->postJson('/api/submissions/'.$draft['id'].'/complete', [
            'version' => 1,
        ])->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('version', 2)
            ->json();

        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 2,
            'values' => [['indicator_id' => $indicator->id, 'value' => 14]],
        ])->assertStatus(409);
        $this->postJson('/api/submissions/'.$draft['id'].'/verify', ['version' => 2])->assertForbidden();

        Sanctum::actingAs($admin);
        $verified = $this->postJson('/api/submissions/'.$completed['id'].'/verify', [
            'version' => 2,
        ])->assertOk()
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('version', 3)
            ->json();

        $this->postJson('/api/submissions/'.$verified['id'].'/unverify', ['version' => 3])
            ->assertUnprocessable();
        $unverified = $this->postJson('/api/submissions/'.$verified['id'].'/unverify', [
            'version' => 3,
            'reason' => 'Perlu koreksi sumber data.',
        ])->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('version', 4)
            ->json();

        Sanctum::actingAs($operator);
        $this->postJson('/api/submissions/'.$unverified['id'].'/reopen', ['version' => 4])
            ->assertUnprocessable();
        $reopened = $this->postJson('/api/submissions/'.$unverified['id'].'/reopen', [
            'version' => 4,
            'reason' => 'Memperbaiki nilai berdasarkan dokumen terbaru.',
        ])->assertOk()
            ->assertJsonPath('status', 'not_started')
            ->assertJsonPath('version', 5)
            ->json();

        $revised = $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => $reopened['version'],
            'revision_reason' => 'Dokumen sumber telah diperbarui.',
            'values' => [['indicator_id' => $indicator->id, 'value' => 14]],
        ])->assertOk()
            ->assertJsonPath('status', 'not_started')
            ->assertJsonPath('version', 6)
            ->json();

        $completedAgain = $this->postJson('/api/submissions/'.$revised['id'].'/complete', [
            'version' => $revised['version'],
        ])->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('version', 7)
            ->json();

        Sanctum::actingAs($admin);
        $this->postJson('/api/submissions/'.$completedAgain['id'].'/verify', [
            'version' => $completedAgain['version'],
        ])->assertOk()
            ->assertJsonPath('status', 'verified')
            ->assertJsonPath('version', 8);

        $this->assertDatabaseHas('submission_events', [
            'submission_id' => $draft['id'],
            'action' => 'unverify',
            'reason' => 'Perlu koreksi sumber data.',
        ]);
        $this->assertDatabaseHas('submission_events', [
            'submission_id' => $draft['id'],
            'action' => 'reopen',
            'reason' => 'Memperbaiki nilai berdasarkan dokumen terbaru.',
        ]);
        $this->assertDatabaseCount('indicator_value_revisions', 2);
        $this->assertDatabaseHas('indicator_value_revisions', [
            'reason' => 'Dokumen sumber telah diperbarui.',
        ]);
    }

    public function test_derived_indicators_cannot_be_written_directly(): void
    {
        [$operator, , $table] = $this->scenario();
        $derived = Indicator::create([
            'reporting_table_id' => $table->id,
            'code' => 'DERIVED',
            'name' => 'Nilai Turunan',
            'data_type' => 'numeric',
            'value_kind' => 'derived',
            'formula' => ['op' => 'add', 'args' => ['I01', 'I01']],
        ]);
        Sanctum::actingAs($operator);

        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => [['indicator_id' => $derived->id, 'value' => 24]],
        ])->assertUnprocessable();
    }

    public function test_table_one_worksheet_shows_all_regions_but_only_operators_region_is_editable(): void
    {
        [$operator, , $table] = $this->scenario();
        Sanctum::actingAs($operator);

        $this->getJson("/api/reporting-tables/{$table->id}/worksheet")
            ->assertOk()
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.editable', true)
            ->assertJsonPath('rows.1.editable', false);
    }

    public function test_partial_draft_save_preserves_other_indicator_not_applicable_state(): void
    {
        [$operator, , $table, $indicator] = $this->scenario();
        $other = Indicator::create([
            'reporting_table_id' => $table->id,
            'code' => 'I02',
            'name' => 'Indikator Lain',
            'data_type' => 'numeric',
        ]);
        Sanctum::actingAs($operator);

        $draft = $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => 0,
            'values' => [
                ['indicator_id' => $indicator->id, 'value' => 12],
                ['indicator_id' => $other->id, 'not_applicable' => true, 'not_applicable_reason' => 'Tidak tersedia di wilayah ini.'],
            ],
        ])->assertOk()->json();

        $this->postJson('/api/submissions/draft', [
            'reporting_table_id' => $table->id,
            'version' => $draft['version'],
            'values' => [['indicator_id' => $indicator->id, 'value' => 13]],
        ])->assertOk();

        $this->assertDatabaseHas('indicator_values', [
            'submission_id' => $draft['id'],
            'indicator_id' => $other->id,
            'not_applicable' => true,
            'not_applicable_reason' => 'Tidak tersedia di wilayah ini.',
        ]);
    }

    private function scenario(): array
    {
        $regionA = Region::create(['code' => 'A', 'name' => 'Kabupaten A']);
        $regionB = Region::create(['code' => 'B', 'name' => 'Kabupaten B']);
        $year = ReportingYear::create(['year' => 2024, 'status' => 'open']);
        $table = ReportingTable::create([
            'reporting_year_id' => $year->id,
            'code' => 'T01',
            'name' => 'Tabel Uji',
        ]);
        $indicator = Indicator::create([
            'reporting_table_id' => $table->id,
            'code' => 'I01',
            'name' => 'Indikator Uji',
            'data_type' => 'numeric',
        ]);
        $operatorA = User::create([
            'name' => 'Operator A',
            'email' => 'operator-a@example.com',
            'password' => Hash::make('secret1'),
            'role' => 'operator',
            'region_id' => $regionA->id,
        ]);
        $operatorB = User::create([
            'name' => 'Operator B',
            'email' => 'operator-b@example.com',
            'password' => Hash::make('secret1'),
            'role' => 'operator',
            'region_id' => $regionB->id,
        ]);

        return [$operatorA, $operatorB, $table, $indicator];
    }
}
