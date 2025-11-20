<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = ['action_by','action_type','model_type','model_id','action','before','after'];
    public function updated_by(){
        return $this->morphTo('updated_by','action_type','action_by');
    }
}
