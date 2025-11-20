<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssessmentData extends Model
{
    use HasFactory;

    protected $table = 'assessment_data';

    protected $fillable = [
        'user_id',
        'scores'
    ];

    protected $casts = [
        'scores' => 'array'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
