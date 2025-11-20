<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SpiritMindsetData extends Model
{
    use HasFactory;

    protected $table = 'spirit_mindset_data';

    protected $fillable = [
        'user_id',
        'date',
        'data'
    ];

    protected $casts = [
        'data' => 'array',
        'date' => 'date'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
