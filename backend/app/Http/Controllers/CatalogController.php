<?php

namespace App\Http\Controllers;

use App\Models\CatalogImport;
use App\Models\Indicator;
use App\Models\IndicatorValue;
use App\Models\Region;
use App\Models\ReportingTable;
use App\Models\ReportingYear;
use App\Models\Submission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CatalogController extends Controller
{
    public function regions()
    {
        return Region::orderBy('name')->get();
    }

    public function years()
    {
        return ReportingYear::withCount('reportingTables')->orderByDesc('year')->get();
    }

    public function tables(ReportingYear $reportingYear)
    {
        return $reportingYear->reportingTables()->with('indicators')->orderBy('position')->get()
            ->each(fn (ReportingTable $table) => $this->hideUnmappedIndicators($table));
    }

    public function table(ReportingTable $reportingTable)
    {
        $reportingTable->load('reportingYear', 'indicators');
        $this->hideUnmappedIndicators($reportingTable);

        return $reportingTable;
    }

    public function importReport(ReportingYear $reportingYear)
    {
        $import = CatalogImport::where('reporting_year_id', $reportingYear->id)
            ->latest('imported_at')
            ->first();

        return $import ?? response()->json(['message' => 'Belum ada impor katalog untuk Tahun Pelaporan ini.'], 404);
    }

    public function storeRegion(Request $request)
    {
        return response()->json(Region::create($request->validate([
            'code' => ['required', 'string', 'max:30', 'unique:regions'],
            'name' => ['required', 'string', 'max:255'],
        ])), 201);
    }

    public function updateRegion(Request $request, Region $region)
    {
        $region->update($request->validate([
            'code' => ['sometimes', 'string', 'max:30', Rule::unique('regions')->ignore($region)],
            'name' => ['sometimes', 'string', 'max:255'],
        ]));

        return $region;
    }

    public function destroyRegion(Region $region)
    {
        abort_if($region->users()->exists() || Submission::where('region_id', $region->id)->exists(), 409, 'Region is in use.');
        $region->delete();

        return response()->noContent();
    }

    public function storeYear(Request $request)
    {
        return response()->json(ReportingYear::create($request->validate([
            'year' => ['required', 'integer', 'between:2000,2100', 'unique:reporting_years'],
            'status' => ['required', Rule::in(['open', 'closed'])],
        ])), 201);
    }

    public function updateYear(Request $request, ReportingYear $reportingYear)
    {
        $reportingYear->update($request->validate([
            'year' => ['sometimes', 'integer', 'between:2000,2100', Rule::unique('reporting_years')->ignore($reportingYear)],
            'status' => ['sometimes', Rule::in(['open', 'closed'])],
        ]));

        return $reportingYear;
    }

    public function destroyYear(ReportingYear $reportingYear)
    {
        abort_if(Submission::where('reporting_year_id', $reportingYear->id)->exists(), 409, 'Reporting year has submissions.');
        $reportingYear->delete();

        return response()->noContent();
    }

    public function storeTable(Request $request)
    {
        $data = $request->validate([
            'reporting_year_id' => ['required', 'exists:reporting_years,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
        $request->validate(['code' => Rule::unique('reporting_tables')->where('reporting_year_id', $data['reporting_year_id'])]);

        return response()->json(ReportingTable::create($data), 201);
    }

    public function updateTable(Request $request, ReportingTable $reportingTable)
    {
        $data = $request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('reporting_tables')->where('reporting_year_id', $reportingTable->reporting_year_id)->ignore($reportingTable)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]);
        $reportingTable->update($data);

        return $reportingTable;
    }

    public function destroyTable(ReportingTable $reportingTable)
    {
        abort_if(Submission::where('reporting_table_id', $reportingTable->id)->exists(), 409, 'Reporting table has submissions.');
        $reportingTable->delete();

        return response()->noContent();
    }

    public function storeIndicator(Request $request)
    {
        $data = $request->validate([
            'reporting_table_id' => ['required', 'exists:reporting_tables,id'],
            'code' => ['required', 'string', 'max:50'],
            'name' => ['required', 'string', 'max:255'],
            'data_type' => ['required', Rule::in(['numeric', 'text', 'date'])],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_required' => ['nullable', 'boolean'],
            'position' => ['nullable', 'integer', 'min:0'],
        ]);
        $request->validate(['code' => Rule::unique('indicators')->where('reporting_table_id', $data['reporting_table_id'])]);

        return response()->json(Indicator::create($data), 201);
    }

    public function updateIndicator(Request $request, Indicator $indicator)
    {
        $indicator->update($request->validate([
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('indicators')->where('reporting_table_id', $indicator->reporting_table_id)->ignore($indicator)],
            'name' => ['sometimes', 'string', 'max:255'],
            'data_type' => ['sometimes', Rule::in(['numeric', 'text', 'date'])],
            'unit' => ['nullable', 'string', 'max:50'],
            'is_required' => ['sometimes', 'boolean'],
            'position' => ['sometimes', 'integer', 'min:0'],
        ]));

        return $indicator;
    }

    public function destroyIndicator(Indicator $indicator)
    {
        abort_if(IndicatorValue::where('indicator_id', $indicator->id)->exists(), 409, 'Indicator has reported values.');
        $indicator->delete();

        return response()->noContent();
    }

    public function mapIndicators(Request $request, ReportingTable $reportingTable)
    {
        abort_if($reportingTable->code === 'T02', 409, 'Tabel 2 dipetakan berdasarkan kelompok umur dari Sheet 2.');
        abort_if(Submission::where('reporting_table_id', $reportingTable->id)->exists(), 409, 'Tabel Pelaporan sudah memiliki data.');
        $data = $request->validate([
            'indicators' => ['required', 'array', 'min:1'],
            'indicators.*.code' => ['required', 'string', 'max:50', 'distinct'],
            'indicators.*.name' => ['required', 'string', 'max:255'],
            'indicators.*.data_type' => ['required', Rule::in(['numeric', 'text', 'date'])],
            'indicators.*.unit' => ['nullable', 'string', 'max:50'],
            'indicators.*.is_required' => ['required', 'boolean'],
        ]);

        $reportingTable->indicators()->delete();
        foreach ($data['indicators'] as $position => $indicator) {
            $reportingTable->indicators()->create([...$indicator, 'position' => $position + 1]);
        }
        $reportingTable->update(['mapping_status' => 'ready']);

        return $reportingTable->load('indicators');
    }

    private function hideUnmappedIndicators(ReportingTable $table): void
    {
        if ($table->mapping_status !== 'ready') {
            $table->setRelation('indicators', collect());
        }
    }
}
