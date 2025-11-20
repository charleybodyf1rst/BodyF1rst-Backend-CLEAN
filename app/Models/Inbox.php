<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inbox extends Model
{
    use HasFactory;


    protected $casts = [
        'coach_id' => 'integer',
        'user_id' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id');
    }
    public function coach()
    {
        return $this->belongsTo(Coach::class,'coach_id');
    }
    public function messages()
    {
        return $this->hasMany(InboxChat::class,'inbox_id','id');
    }
    public function last_message(){
        return $this->hasOne(InboxChat::class,"inbox_id","id")->orderBy("created_at","desc");
    }
}
