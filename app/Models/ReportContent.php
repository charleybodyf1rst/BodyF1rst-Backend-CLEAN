<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportContent extends Model
{
    use HasFactory;

    protected $fillable = ['model_id','model_type','reason'];

    protected $casts = [
        "user_id" => "integer",
        "model_id" => "integer",
    ];
}
