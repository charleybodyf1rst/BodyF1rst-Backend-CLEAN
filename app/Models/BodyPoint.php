<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BodyPoint extends Model
{
    use HasFactory;

    protected $fillable = ['meta_value','meta_key'];

    protected $casts = [
        "meta_value" => 'array'
    ];

    public function getMetaValueAttribute($value)
    {
        return json_decode($value, true);
    }
}
