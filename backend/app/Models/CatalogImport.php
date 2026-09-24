<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CatalogImport extends Model
{
    protected $fillable = ['reporting_year_id', 'filename', 'checksum', 'report', 'imported_at'];

    protected function casts(): array
    {
        return ['report' => 'array', 'imported_at' => 'datetime'];
    }

    public function reportingYear()
    {
        return $this->belongsTo(ReportingYear::class);
    }
}
