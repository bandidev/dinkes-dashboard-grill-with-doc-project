<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Submission extends Model
{
    protected $fillable = [
        'region_id', 'reporting_year_id', 'reporting_table_id', 'status', 'version',
        'completed_at', 'completed_by', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'verified_at' => 'datetime'];
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function reportingYear()
    {
        return $this->belongsTo(ReportingYear::class);
    }

    public function reportingTable()
    {
        return $this->belongsTo(ReportingTable::class);
    }

    public function values()
    {
        return $this->hasMany(IndicatorValue::class);
    }

    public function events()
    {
        return $this->hasMany(SubmissionEvent::class);
    }
}
