<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    const Body_Points = 'Body Points';

    protected $fillable = [
        "user_id",
        "type",
        "transaction_type",
        "transaction_date",
        "name",
        "description",
        "points",
    ];
    protected $casts = [
        'user_id' => 'integer',
    ];

    public function user(){
        return $this->belongsTo(User::class,"user_id","id");
    }
}
