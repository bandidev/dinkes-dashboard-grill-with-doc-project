<?php

namespace App\Console\Commands;

use App\Services\WorkbookCatalogImporter;
use Illuminate\Console\Command;
use Throwable;

class ImportProfileCatalog extends Command
{
    protected $signature = 'profile:import-catalog {workbook} {--year=2024} {--replace}';

    protected $description = 'Impor struktur Tabel Pelaporan dari workbook Profil Kesehatan';

    public function handle(WorkbookCatalogImporter $importer): int
    {
        try {
            $import = $importer->import(
                realpath($this->argument('workbook')) ?: $this->argument('workbook'),
                (int) $this->option('year'),
                (bool) $this->option('replace'),
            );
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $report = $import->report;
        $this->info("{$report['reporting_tables']} Tabel Pelaporan diimpor dari {$report['workbook_sheets']} lembar workbook.");
        $this->warn("{$report['mapping_pending']} tabel berstatus Perlu Pemetaan Indikator.");

        return self::SUCCESS;
    }
}
