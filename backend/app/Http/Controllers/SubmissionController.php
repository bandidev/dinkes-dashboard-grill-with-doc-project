<?php

namespace App\Http\Controllers;

use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\IndicatorValueRevision;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\Submission;
use App\Models\SubmissionEvent;
use App\Services\IndicatorCalculator;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

        $submissions = Submission::withCount(['values as values_count' => fn ($query) => $query->whereHas('indicator', fn ($indicator) => $indicator->where('is_active', true)->where('is_required', true)->where('value_kind', 'base'))])
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

            $indicators = $table->indicators->keyBy('id');
            foreach ($data['values'] as $item) {
                $indicator = $indicators->get($item['indicator_id']);
                $attributes = $this->valueAttributes($item, $indicator);
                $value = IndicatorValue::firstOrNew([
                    'submission_id' => $submission->id,
                    'indicator_id' => $indicator->id,
                ]);
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
                    'numeric' => ['nullable', 'numeric'],
                    'date' => ['nullable', 'date_format:Y-m-d'],
                    default => ['nullable', 'string'],
                } : ['nullable'],
                'not_applicable_reason' => [function ($attribute, $value, $fail) use ($item) {
                    if (($item['not_applicable'] ?? false) && blank($value)) {
                        $fail('A reason is required when an indicator is not applicable.');
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

        return array_map(function (array $item) use ($indicators) {
            $indicator = $indicators->get($item['indicator_id']);
            if ($indicator?->data_type !== 'numeric' || ! is_string($item['value'] ?? null)) {
                return $item;
            }

            $value = trim($item['value']);
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
            ->filter(function (Indicator $indicator) use ($values) {
                $value = $values->get($indicator->id);

                return ! $value || ($value->not_applicable
                    ? blank($value->not_applicable_reason)
                    : blank($value->{$indicator->data_type.'_value'}));
            })
            ->pluck('code');

        abort_if($missing->isNotEmpty(), 422, 'Required indicators are incomplete: '.$missing->join(', '));
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
