<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrganizationSubmission extends Model
{
    use HasFactory;

    protected $casts = [
        "organization_id" => "integer"
    ];

    public function getFileAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null) {
            return url("/") . "/upload/organization_submissions/{$value}";
        } else {
            return null;
        }
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class,'organization_id');
    }
}
