<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\IndicatorValueRevision;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Models\TableFiveProvinceDenominator;
use App\Services\IndicatorCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SubmissionController extends Controller
{
    private const VALUE_FIELDS = ['numeric_value', 'text_value', 'date_value', 'not_applicable', 'not_applicable_reason'];

    public function worksheet(Request $request, ReportingTable $reportingTable, IndicatorCalculator $calculator)
    {
        abort_unless($reportingTable->code === 'T01' && $reportingTable->mapping_status === 'ready', 404);
        $reportingTable->load('reportingYear', 'indicators');
        $submissions = Submission::with('values')
            ->where('reporting_table_id', $reportingTable->id)
            ->get()
            ->keyBy('region_id');

        return [
            'table' => $reportingTable,
            'rows' => Region::orderBy('id')->get()->map(function (Region $region) use ($request, $reportingTable, $submissions, $calculator) {
                $submission = $submissions->get($region->id);
                $values = $submission?->values ?? collect();

                return [
                    'region' => $region,
                    'submission_id' => $submission?->id,
                    'status' => $submission?->status ?? 'not_started',
                    'version' => $submission?->version ?? 0,
                    'editable' => $request->user()->role === 'operator'
                        && $request->user()->region_id === $region->id
                        && ($submission?->status ?? 'not_started') === 'not_started',
                    'values' => $values->keyBy('indicator_id')->map(fn (IndicatorValue $value) => $value->numeric_value),
                    'calculated_values' => $calculator->calculate($reportingTable->indicators, $values),
                ];
            }),
        ];
    }

    public function province(ReportingTable $reportingTable, IndicatorCalculator $calculator)
    {
        abort_unless(in_array($reportingTable->code, ['T02', 'T03', 'T04', 'T05', 'T06'], true) && $reportingTable->mapping_status === 'ready', 404);
        $reportingTable->load('indicators');
        $regions = ($reportingTable->code === 'T06'
            ? Region::whereIn('code', ['BANGKA', 'BELITUNG', 'BANGKA_BARAT', 'BANGKA_TENGAH', 'BANGKA_SELATAN', 'BELITUNG_TIMUR', 'PANGKALPINANG'])
            : Region::query())->pluck('id');
        $baseCount = $reportingTable->indicators->where('value_kind', 'base')->count();
        $isFacilityTable = in_array($reportingTable->code, ['T04', 'T05', 'T06'], true);
        $submissions = Submission::with('values')
            ->where('reporting_table_id', $reportingTable->id)
            ->whereIn('region_id', $regions)
            ->get()
            ->keyBy('region_id');
        $regionValues = $submissions->map(fn (Submission $submission) => $submission->values->keyBy('indicator_id'));
        $values = collect();
        foreach ($reportingTable->indicators->where('value_kind', 'base') as $indicator) {
            if ($regions->isEmpty() || ($reportingTable->code === 'T06' && $regions->count() !== 7)) {
                continue;
            }
            $sum = 0;
            foreach ($regions as $regionId) {
                $value = $regionValues->get($regionId)?->get($indicator->id);
                if (! $value || $value->not_applicable || $value->numeric_value === null) {
                    if ($isFacilityTable) {
                        $sum = null;
                        break;
                    }

                    continue 2;
                }
                $sum += in_array($reportingTable->code, ['T05', 'T06'], true)
                    ? (int) $value->numeric_value
                    : (float) $value->numeric_value;
            }
            if ($isFacilityTable && $sum === null) {
                continue;
            }
            $values->push(new IndicatorValue(['indicator_id' => $indicator->id, 'numeric_value' => $sum]));
        }

        $response = [
            'table' => $reportingTable,
            'values' => $values->pluck('numeric_value', 'indicator_id'),
            'calculated_values' => $calculator->calculate($reportingTable->indicators, $values),
            'complete_base_count' => $values->count(),
            'base_count' => $baseCount,
            'region_count' => $regions->count(),
            'complete_region_count' => in_array($reportingTable->code, ['T05', 'T06'], true) ? $submissions->whereIn('status', ['completed', 'verified'])->count() : 0,
        ];

        if ($reportingTable->code === 'T05') {
            $denominator = TableFiveProvinceDenominator::where('reporting_year_id', $reportingTable->reporting_year_id)->first()
                ?? new TableFiveProvinceDenominator(['reporting_year_id' => $reportingTable->reporting_year_id]);
            $denominatorValues = [
                'outpatient_l' => $denominator->outpatient_l,
                'outpatient_p' => $denominator->outpatient_p,
                'inpatient_l' => $denominator->inpatient_l,
                'inpatient_p' => $denominator->inpatient_p,
            ];
            $denominatorValues['outpatient_total'] = $denominatorValues['outpatient_l'] !== null && $denominatorValues['outpatient_p'] !== null
                ? $denominatorValues['outpatient_l'] + $denominatorValues['outpatient_p']
                : null;
            $denominatorValues['inpatient_total'] = $denominatorValues['inpatient_l'] !== null && $denominatorValues['inpatient_p'] !== null
                ? $denominatorValues['inpatient_l'] + $denominatorValues['inpatient_p']
                : null;
            $mainTotal = [];
            foreach ([
                'outpatient_l' => 'RAWAT_JALAN_L',
                'outpatient_p' => 'RAWAT_JALAN_P',
                'inpatient_l' => 'RAWAT_INAP_L',
                'inpatient_p' => 'RAWAT_INAP_P',
            ] as $key => $measure) {
                $indicator = $reportingTable->indicators->firstWhere('code', "KUNJUNGAN_TOTAL_UTAMA_{$measure}");
                $mainTotal[$key] = $indicator ? $response['calculated_values'][$indicator->id] ?? null : null;
            }
            foreach (['outpatient_total' => 'RAWAT_JALAN_LP', 'inpatient_total' => 'RAWAT_INAP_LP'] as $key => $measure) {
                $indicator = $reportingTable->indicators->firstWhere('code', "KUNJUNGAN_TOTAL_UTAMA_{$measure}");
                $mainTotal[$key] = $indicator ? $response['calculated_values'][$indicator->id] ?? null : null;
            }
            $response['province_denominators'] = $denominatorValues;
            $response['coverage_values'] = collect($mainTotal)->map(fn ($numerator, $key) => $numerator === null || ! ($denominatorValues[$key] ?? null)
                    ? null
                    : round($numerator / $denominatorValues[$key] * 100, 2)
            )->all();
        }

        return $response;
    }

    public function updateTableFiveProvinceDenominators(Request $request, ReportingTable $reportingTable)
    {
        abort_unless($reportingTable->code === 'T05' && $reportingTable->mapping_status === 'ready', 404);
        $data = $request->validate([
            'outpatient_l' => ['present', 'nullable', 'integer', 'min:0'],
            'outpatient_p' => ['present', 'nullable', 'integer', 'min:0'],
            'inpatient_l' => ['present', 'nullable', 'integer', 'min:0'],
            'inpatient_p' => ['present', 'nullable', 'integer', 'min:0'],
        ]);
        abort_unless($reportingTable->reportingYear()->value('status') === 'open', 409, 'The reporting year is closed.');
        $denominator = DB::transaction(function () use ($request, $reportingTable, $data) {
            $denominator = TableFiveProvinceDenominator::where('reporting_year_id', $reportingTable->reporting_year_id)->lockForUpdate()->first()
                ?? TableFiveProvinceDenominator::create(['reporting_year_id' => $reportingTable->reporting_year_id]);
            $oldValues = Arr::only($denominator->getAttributes(), array_keys($data));
            $denominator->fill([...$data, 'updated_by' => $request->user()->id]);
            if ($denominator->isDirty(array_keys($data))) {
                $denominator->save();
                DB::table('table_five_province_denominator_revisions')->insert([
                    'denominator_id' => $denominator->id,
                    'user_id' => $request->user()->id,
                    'old_values' => json_encode($oldValues),
                    'new_values' => json_encode($data),
                    'reason' => 'Pembaruan Nilai Dasar penyebut Provinsi Tabel Pelaporan 5.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            return $denominator->refresh();
        });

        return $denominator;
    }

    public function index(Request $request)
    {
        $data = $request->validate([
            'reporting_year_id' => ['required', 'exists:reporting_years,id'],
            'region_id' => ['nullable', 'exists:regions,id'],
        ]);
        $user = $request->user();
        if ($user->role === 'administrator' && ! isset($data['region_id'])) {
            return $this->allRegionsIndex((int) $data['reporting_year_id']);
        }

        $regionId = $user->role === 'operator' ? $user->region_id : $data['region_id'];
        abort_unless($regionId, 422, 'The user must be assigned to a region.');

        $submissions = Submission::withCount(['values as values_count' => fn ($query) => $query->whereHas('indicator', fn ($indicator) => $indicator->where('is_active', true)->where('is_required', true)->where('value_kind', 'base'))->where(fn ($value) => $value->whereNotNull('numeric_value')->orWhereNotNull('text_value')->orWhereNotNull('date_value')->orWhere(fn ($na) => $na->where('not_applicable', true)->whereHas('indicator', fn ($indicator) => $indicator->whereNotIn('reporting_table_id', ReportingTable::whereIn('code', ['T01', 'T02', 'T03', 'T04', 'T05', 'T06'])->select('id')))))])
            ->where('reporting_year_id', $data['reporting_year_id'])
            ->where('region_id', $regionId)
            ->get()
            ->keyBy('reporting_table_id');

        return ReportingTable::with('indicators')
            ->where('reporting_year_id', $data['reporting_year_id'])
            ->orderBy('position')
            ->get()
            ->map(function (ReportingTable $table) use ($submissions, $regionId) {
                if ($table->mapping_status !== 'ready') {
                    $table->setRelation('indicators', collect());
                }
                $submission = $submissions->get($table->id);

                return [
                    'id' => $submission?->id,
                    'region_id' => (int) $regionId,
                    'reporting_year_id' => $table->reporting_year_id,
                    'reporting_table' => $table,
                    'status' => $submission?->status ?? 'not_started',
                    'version' => $submission?->version ?? 0,
                    'values_count' => $submission?->values_count ?? 0,
                    'completed_at' => $submission?->completed_at,
                    'verified_at' => $submission?->verified_at,
                    'updated_at' => $submission?->updated_at,
                ];
            });
    }

    private function allRegionsIndex(int $reportingYearId)
    {
        $regionCount = Region::count();
        $counts = Submission::query()
            ->selectRaw('reporting_table_id, status, count(*) as total')
            ->where('reporting_year_id', $reportingYearId)
            ->groupBy('reporting_table_id', 'status')
            ->get()
            ->groupBy('reporting_table_id');

        return ReportingTable::with('indicators')
            ->where('reporting_year_id', $reportingYearId)
            ->orderBy('position')
            ->get()
            ->map(function (ReportingTable $table) use ($counts, $regionCount) {
                if ($table->mapping_status !== 'ready') {
                    $table->setRelation('indicators', collect());
                }

                $statuses = $counts->get($table->id, collect())->pluck('total', 'status');
                $verified = (int) ($statuses['verified'] ?? 0);
                $completed = (int) ($statuses['completed'] ?? 0);
                $notStarted = max(0, $regionCount - $verified - $completed);

                return [
                    'id' => null,
                    'region_id' => null,
                    'reporting_year_id' => $table->reporting_year_id,
                    'reporting_table' => $table,
                    'status' => $regionCount > 0 && $verified === $regionCount
                        ? 'verified'
                        : ($verified + $completed > 0 ? 'completed' : 'not_started'),
                    'version' => 0,
                    'values_count' => 0,
                    'region_counts' => [
                        'total' => $regionCount,
                        'verified' => $verified,
                        'completed' => $completed,
                        'not_started' => $notStarted,
                    ],
                    'completed_at' => null,
                    'verified_at' => null,
                    'updated_at' => null,
                ];
            });
    }

    public function show(Request $request, Submission $submission, IndicatorCalculator $calculator)
    {
        $this->authorizeRegion($request, $submission);

        $submission->load(
            'region',
            'reportingYear',
            'reportingTable.indicators',
            'values.indicator',
            'values.revisions',
            'events'
        );
        if ($submission->reportingTable->mapping_status !== 'ready') {
            $submission->reportingTable->setRelation('indicators', collect());
            $submission->setRelation('values', collect());
        } else {
            $activeIndicatorIds = $submission->reportingTable->indicators->modelKeys();
            $submission->setRelation('values', $submission->values->whereIn('indicator_id', $activeIndicatorIds)->values());
            $submission->setAttribute(
                'calculated_values',
                $calculator->calculate($submission->reportingTable->indicators, $submission->values),
            );
        }

        return $submission;
    }

    public function saveDraft(Request $request, IndicatorCalculator $calculator)
    {
        $data = $request->validate([
            'reporting_table_id' => ['required', 'exists:reporting_tables,id'],
            'version' => ['required', 'integer', 'min:0'],
            'revision_reason' => ['nullable', 'string', 'max:1000'],
            'values' => ['required', 'array'],
            'values.*.indicator_id' => ['required', 'integer', 'distinct', 'exists:indicators,id'],
            'values.*.value' => ['nullable'],
            'values.*.not_applicable' => ['nullable', 'boolean'],
            'values.*.not_applicable_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $table = ReportingTable::with('reportingYear', 'indicators')->findOrFail($data['reporting_table_id']);
        abort_unless($table->mapping_status === 'ready', 409, 'Indikator Tabel Pelaporan masih perlu dipetakan.');
        abort_unless($table->reportingYear->status === 'open', 409, 'The reporting year is closed.');
        $data['values'] = $this->normalizeNumericValues($data['values'], $table);
        $this->validateValues($data['values'], $table);

        $submission = DB::transaction(function () use ($request, $data, $table) {
            $submission = Submission::where([
                'region_id' => $request->user()->region_id,
                'reporting_year_id' => $table->reporting_year_id,
                'reporting_table_id' => $table->id,
            ])->lockForUpdate()->first();

            if (! $submission) {
                abort_if($data['version'] !== 0, 409, 'The submission version is stale.');
                $submission = Submission::create([
                    'region_id' => $request->user()->region_id,
                    'reporting_year_id' => $table->reporting_year_id,
                    'reporting_table_id' => $table->id,
                    'status' => 'not_started',
                    'version' => 0,
                ]);
            }

            abort_unless($submission->status === 'not_started', 409, 'The submission is locked.');
            abort_if($submission->version !== $data['version'], 409, 'The submission version is stale.');

            if ($table->code === 'T06') {
                $this->validateTableSixPairs($submission, $table, $data['values']);
            }

            $indicators = $table->indicators->keyBy('id');
            foreach ($data['values'] as $item) {
                $indicator = $indicators->get($item['indicator_id']);
                $value = IndicatorValue::firstOrNew([
                    'submission_id' => $submission->id,
                    'indicator_id' => $indicator->id,
                ]);
                if (in_array($table->code, ['T01', 'T02', 'T03', 'T04', 'T05', 'T06'], true) && $value->not_applicable && blank($item['value'] ?? null)) {
                    $item['value'] = 0;
                }
                $attributes = $this->valueAttributes($item, $indicator);
                $oldValue = $value->exists ? Arr::only($value->getAttributes(), self::VALUE_FIELDS) : null;
                $value->fill($attributes);

                if (! $value->exists || $value->isDirty(self::VALUE_FIELDS)) {
                    $value->save();
                    IndicatorValueRevision::create([
                        'indicator_value_id' => $value->id,
                        'user_id' => $request->user()->id,
                        'old_value' => $oldValue,
                        'new_value' => Arr::only($value->getAttributes(), self::VALUE_FIELDS),
                        'reason' => $data['revision_reason'] ?? null,
                    ]);
                }
            }

            if (in_array($table->code, ['T01', 'T02', 'T03', 'T04', 'T05', 'T06'], true)) {
                $submitted = collect($data['values'])->pluck('indicator_id');
                $legacyValues = IndicatorValue::where('submission_id', $submission->id)
                    ->where('not_applicable', true)
                    ->whereNotIn('indicator_id', $submitted)
                    ->whereHas('indicator', fn ($query) => $query->where('value_kind', 'base')->where('is_active', true))
                    ->get();
                foreach ($legacyValues as $value) {
                    $oldValue = Arr::only($value->getAttributes(), self::VALUE_FIELDS);
                    $value->update(['numeric_value' => 0, 'not_applicable' => false, 'not_applicable_reason' => null]);
                    IndicatorValueRevision::create([
                        'indicator_value_id' => $value->id,
                        'user_id' => $request->user()->id,
                        'old_value' => $oldValue,
                        'new_value' => Arr::only($value->getAttributes(), self::VALUE_FIELDS),
                        'reason' => $data['revision_reason'] ?? 'Nilai lama Tidak Berlaku diperbarui menjadi 0 pada simpan draft.',
                    ]);
                }
            }

            $submission->increment('version');

            return $submission->refresh();
        });

        $submission->load('reportingTable.indicators', 'values.indicator');
        $submission->setAttribute(
            'calculated_values',
            $calculator->calculate($submission->reportingTable->indicators, $submission->values),
        );

        return response()->json($submission);
    }

    public function complete(Request $request, Submission $submission)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:0']]);
        $this->authorizeRegion($request, $submission);
        $this->requireOpenYear($submission);
        abort_unless($submission->reportingTable()->value('mapping_status') === 'ready', 409, 'Indikator Tabel Pelaporan masih perlu dipetakan.');
        abort_unless($submission->status === 'not_started', 409, 'Only draft submissions can be completed.');
        abort_if($submission->version !== $data['version'], 409, 'The submission version is stale.');
        $this->assertComplete($submission);

        return $this->transition($request, $submission, $data['version'], 'complete', 'completed', [
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);
    }

    public function reopen(Request $request, Submission $submission)
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        $this->authorizeRegion($request, $submission);
        $this->requireOpenYear($submission);
        abort_unless($submission->status === 'completed', 409, 'Only completed submissions can be reopened.');
        abort_if($submission->version !== $data['version'], 409, 'The submission version is stale.');

        return $this->transition($request, $submission, $data['version'], 'reopen', 'not_started', [
            'completed_at' => null,
            'completed_by' => null,
        ], $data['reason']);
    }

    public function verify(Request $request, Submission $submission)
    {
        $data = $request->validate(['version' => ['required', 'integer', 'min:0']]);
        abort_unless($submission->status === 'completed', 409, 'Only completed submissions can be verified.');
        abort_if($submission->version !== $data['version'], 409, 'The submission version is stale.');

        return $this->transition($request, $submission, $data['version'], 'verify', 'verified', [
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);
    }

    public function unverify(Request $request, Submission $submission)
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'max:1000'],
        ]);
        abort_unless($submission->status === 'verified', 409, 'Only verified submissions can be unverified.');
        abort_if($submission->version !== $data['version'], 409, 'The submission version is stale.');

        return $this->transition($request, $submission, $data['version'], 'unverify', 'completed', [
            'verified_at' => null,
            'verified_by' => null,
        ], $data['reason']);
    }

    private function authorizeRegion(Request $request, Submission $submission): void
    {
        abort_if(
            $request->user()->role === 'operator' && $request->user()->region_id !== $submission->region_id,
            403,
            'You may only access your region.'
        );
    }

    private function requireOpenYear(Submission $submission): void
    {
        abort_unless($submission->reportingYear()->value('status') === 'open', 409, 'The reporting year is closed.');
    }

    private function validateValues(array $values, ReportingTable $table): void
    {
        $indicators = $table->indicators->keyBy('id');

        foreach ($values as $index => $item) {
            $indicator = $indicators->get($item['indicator_id']);
            Validator::make($item, [
                'indicator_id' => [function ($attribute, $value, $fail) use ($indicator) {
                    if (! $indicator) {
                        $fail('The indicator does not belong to this reporting table.');
                    } elseif ($indicator->value_kind !== 'base') {
                        $fail('Nilai Turunan tidak dapat diinput langsung.');
                    }
                }],
                'value' => $indicator ? match ($indicator->data_type) {
                    'numeric' => in_array($table->code, ['T05', 'T06'], true) ? ['nullable', 'integer', 'min:0'] : ['nullable', 'numeric'],
                    'date' => ['nullable', 'date_format:Y-m-d'],
                    default => ['nullable', 'string'],
                } : ['nullable'],
                'not_applicable_reason' => [function ($attribute, $value, $fail) use ($item) {
                    if (($item['not_applicable'] ?? false) && blank($value)) {
                        $fail('A reason is required when an indicator is not applicable.');
                    }
                }],
                'not_applicable' => [function ($attribute, $value, $fail) use ($item, $table) {
                    if (in_array($table->code, ['T01', 'T02', 'T03', 'T04', 'T05', 'T06'], true) && ($item['not_applicable'] ?? false)) {
                        $fail('Tidak Berlaku tidak tersedia untuk tabel numerik ini. Isi 0 jika nilainya nol.');
                    }
                }],
            ], [], [
                'value' => "values.$index.value",
                'not_applicable_reason' => "values.$index.not_applicable_reason",
            ])->validate();
        }
    }

    private function normalizeNumericValues(array $values, ReportingTable $table): array
    {
        $indicators = $table->indicators->keyBy('id');

        return array_map(function (array $item) use ($indicators, $table) {
            $indicator = $indicators->get($item['indicator_id']);
            if ($indicator?->data_type !== 'numeric' || ! is_string($item['value'] ?? null)) {
                return $item;
            }

            $value = trim($item['value']);
            if (in_array($table->code, ['T05', 'T06'], true)) {
                $item['value'] = $value;

                return $item;
            }
            if (str_contains($value, ',')) {
                $value = str_replace(['.', ','], ['', '.'], $value);
            }
            $item['value'] = $value;

            return $item;
        }, $values);
    }

    private function valueAttributes(array $item, Indicator $indicator): array
    {
        $notApplicable = (bool) ($item['not_applicable'] ?? false);
        $attributes = [
            'numeric_value' => null,
            'text_value' => null,
            'date_value' => null,
            'not_applicable' => $notApplicable,
            'not_applicable_reason' => $notApplicable ? $item['not_applicable_reason'] : null,
        ];

        if (! $notApplicable) {
            $attributes[$indicator->data_type.'_value'] = $item['value'] ?? null;
        }

        return $attributes;
    }

    private function assertComplete(Submission $submission): void
    {
        $submission->load('reportingTable.indicators', 'values');
        $values = $submission->values->keyBy('indicator_id');
        $missing = $submission->reportingTable->indicators
            ->where('is_required', true)
            ->filter(function (Indicator $indicator) use ($values, $submission) {
                $value = $values->get($indicator->id);

                return ! $value || (in_array($submission->reportingTable->code, ['T01', 'T02', 'T03', 'T04', 'T05', 'T06'], true) && $value->not_applicable)
                    || ($value->not_applicable
                    ? blank($value->not_applicable_reason)
                    : blank($value->{$indicator->data_type.'_value'}));
            })
            ->pluck('code');

        abort_if($missing->isNotEmpty(), 422, 'Required indicators are incomplete: '.$missing->join(', '));
    }

    private function validateTableSixPairs(Submission $submission, ReportingTable $table, array $submitted): void
    {
        $submittedValues = collect($submitted)->keyBy('indicator_id');
        $storedValues = $submission->values()->get()->keyBy('indicator_id');
        foreach (['UMUM', 'KHUSUS'] as $kind) {
            $values = [];
            foreach (["RS_{$kind}_JUMLAH", "RS_{$kind}_MAMPU_GADAR_LEVEL_I"] as $code) {
                $indicator = $table->indicators->firstWhere('code', $code);
                $item = $indicator ? $submittedValues->get($indicator->id) : null;
                $stored = $indicator ? $storedValues->get($indicator->id) : null;
                $value = $item !== null
                    ? ($item['value'] ?? null)
                    : ($stored && ! $stored->not_applicable ? $stored->numeric_value : null);
                $values[] = blank($value) ? null : (int) $value;
            }

            if ($values[0] !== null && $values[1] !== null && $values[1] > $values[0]) {
                throw ValidationException::withMessages([
                    'values' => 'Jumlah rumah sakit yang mampu Gadar Level I tidak boleh melebihi jumlah rumah sakit.',
                ]);
            }
        }
    }

    private function transition(
        Request $request,
        Submission $submission,
        int $expectedVersion,
        string $action,
        string $toStatus,
        array $attributes,
        ?string $reason = null,
    ): Submission {
        return DB::transaction(function () use ($request, $submission, $expectedVersion, $action, $toStatus, $attributes, $reason) {
            $submission = Submission::lockForUpdate()->findOrFail($submission->id);
            abort_if($submission->version !== $expectedVersion, 409, 'The submission version is stale.');
            $fromStatus = $submission->status;
            $submission->update([...$attributes, 'status' => $toStatus, 'version' => $submission->version + 1]);
            SubmissionEvent::create([
                'submission_id' => $submission->id,
                'user_id' => $request->user()->id,
                'action' => $action,
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
                'reason' => $reason,
            ]);

            return $submission->refresh();
        });
    }
}
