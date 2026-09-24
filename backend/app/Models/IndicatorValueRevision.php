<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorValueRevision extends Model
{
    protected $fillable = ['indicator_value_id', 'user_id', 'old_value', 'new_value', 'reason'];

    protected function casts(): array
    {
        return ['old_value' => 'array', 'new_value' => 'array'];
    }
}
