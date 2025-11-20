<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AssignPlan extends Model
{
    use HasFactory;


    public function upload_by()
    {
        return $this->morphTo('upload_by','uploader','uploaded_by');
    }
}
