<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NutritionCalculation extends Model
{
    use HasFactory;

    protected $fillable = ['meta_key','meta_value'];

    protected $cast = [
        "meta_value" => 'array'
    ];

    public function getMetaValueAttribute($value)
    {
        return json_decode($value, true);
    }
}
