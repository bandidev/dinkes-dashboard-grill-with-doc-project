<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorValue extends Model
{
    protected $fillable = [
        'submission_id', 'indicator_id', 'numeric_value', 'text_value', 'date_value',
        'not_applicable', 'not_applicable_reason',
    ];

    protected function casts(): array
    {
        return ['date_value' => 'date:Y-m-d', 'not_applicable' => 'boolean'];
    }

    public function indicator()
    {
        return $this->belongsTo(Indicator::class);
    }

    public function revisions()
    {
        return $this->hasMany(IndicatorValueRevision::class);
    }
}
