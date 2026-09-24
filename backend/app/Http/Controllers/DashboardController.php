<?php

namespace App\Http\Controllers;

use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\Submission;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $data = $request->validate([
            'reporting_year_id' => ['nullable', 'exists:reporting_years,id'],
            'region_id' => ['nullable', 'exists:regions,id'],
        ]);
        $year = isset($data['reporting_year_id'])
            ? ReportingYear::findOrFail($data['reporting_year_id'])
            : ReportingYear::orderByDesc('year')->firstOrFail();
        $user = $request->user();

        if ($user->role === 'operator') {
            $regions = Region::whereKey($user->region_id)->get();
        } elseif (isset($data['region_id'])) {
            $regions = Region::whereKey($data['region_id'])->get();
        } else {
            $regions = Region::orderBy('name')->get();
        }

        $tableCount = ReportingTable::where('reporting_year_id', $year->id)->count();
        $counts = Submission::query()
            ->selectRaw('region_id, status, count(*) as total')
            ->where('reporting_year_id', $year->id)
            ->whereIn('region_id', $regions->modelKeys())
            ->groupBy('region_id', 'status')
            ->get()
            ->groupBy('region_id');

        $byRegion = $regions->map(function (Region $region) use ($counts, $tableCount) {
            $regionCounts = $counts->get($region->id, collect())->pluck('total', 'status');
            $completed = (int) ($regionCounts['completed'] ?? 0);
            $verified = (int) ($regionCounts['verified'] ?? 0);

            return [
                'region' => $region,
                'total_tables' => $tableCount,
                'not_started' => max(0, $tableCount - $completed - $verified),
                'completed' => $completed,
                'verified' => $verified,
            ];
        });

        $submissions = Submission::where('reporting_year_id', $year->id)
            ->whereIn('region_id', $regions->modelKeys())
            ->get();
        $reportingTables = ReportingTable::where('reporting_year_id', $year->id)
            ->orderBy('position')
            ->get()
            ->map(function (ReportingTable $table) use ($submissions, $regions) {
                $tableSubmissions = $submissions->where('reporting_table_id', $table->id);
                $verified = $tableSubmissions->where('status', 'verified')->count();
                $completed = $tableSubmissions->where('status', 'completed')->count();
                $covered = $verified + $completed;

                return [
                    'id' => $table->id,
                    'code' => $table->code,
                    'name' => $table->name,
                    'position' => $table->position,
                    'status' => $regions->isNotEmpty() && $verified === $regions->count()
                        ? 'verified'
                        : ($covered > 0 ? 'completed' : 'not_started'),
                    'completion' => $regions->isEmpty() ? 0 : (int) round($covered / $regions->count() * 100),
                    'verified_regions' => $verified,
                    'total_regions' => $regions->count(),
                ];
            });
        $recent = $submissions->sortByDesc('updated_at')->take(6)->map(function (Submission $submission) {
            $submission->loadMissing('reportingTable', 'region');

            return [
                'id' => $submission->reporting_table_id,
                'submission_id' => $submission->id,
                'code' => $submission->reportingTable->code,
                'name' => $submission->reportingTable->name,
                'position' => $submission->reportingTable->position,
                'status' => $submission->status,
                'region' => $submission->region->name,
                'updated_at' => $submission->updated_at,
            ];
        })->values();

        return [
            'reporting_year' => $year,
            'summary' => [
                'total_tables' => $tableCount * $regions->count(),
                'not_started' => $byRegion->sum('not_started'),
                'completed' => $byRegion->sum('completed'),
                'verified' => $byRegion->sum('verified'),
            ],
            'regions' => $byRegion,
            'reporting_tables' => $reportingTables,
            'recent_submissions' => $recent,
        ];
    }
}
