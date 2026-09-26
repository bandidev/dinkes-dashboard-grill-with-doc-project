<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TableFiveProvinceDenominator extends Model
{
    protected $fillable = [
        'reporting_year_id', 'outpatient_l', 'outpatient_p', 'inpatient_l', 'inpatient_p', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'outpatient_l' => 'integer',
            'outpatient_p' => 'integer',
            'inpatient_l' => 'integer',
            'inpatient_p' => 'integer',
        ];
    }
}
