<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingTable extends Model
{
    protected $fillable = [
        'reporting_year_id', 'code', 'name', 'description', 'source_sheet',
        'mapping_status', 'source_metadata', 'position',
    ];

    protected function casts(): array
    {
        return ['source_metadata' => 'array'];
    }

    public function indicators()
    {
        return $this->hasMany(Indicator::class)->where('is_active', true)->orderBy('position');
    }

    public function allIndicators()
    {
        return $this->hasMany(Indicator::class)->orderBy('position');
    }

    public function reportingYear()
    {
        return $this->belongsTo(ReportingYear::class);
    }

    public function submissions()
    {
        return $this->hasMany(Submission::class);
    }
}
