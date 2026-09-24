<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportingYear extends Model
{
    protected $fillable = ['year', 'status'];

    public function reportingTables()
    {
        return $this->hasMany(ReportingTable::class);
    }
}
