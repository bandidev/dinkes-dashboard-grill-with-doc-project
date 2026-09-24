<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubmissionEvent extends Model
{
    protected $fillable = ['submission_id', 'user_id', 'action', 'from_status', 'to_status', 'reason'];
}
