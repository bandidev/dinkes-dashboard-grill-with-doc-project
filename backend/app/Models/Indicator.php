<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Indicator extends Model
{
    protected $fillable = [
        'reporting_table_id', 'code', 'name', 'data_type', 'value_kind', 'formula', 'categories',
        'unit', 'decimal_places', 'is_required', 'is_active', 'position',
    ];

    protected function casts(): array
    {
        return [
            'formula' => 'array',
            'categories' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
