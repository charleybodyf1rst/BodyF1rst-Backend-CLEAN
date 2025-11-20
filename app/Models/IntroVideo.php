<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class IntroVideo extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['video_title','type'];

    protected $casts = [
        'is_active' => 'integer'
    ];

    public function getVideoAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null) {
            return url("/") . "/upload/intro_videos/{$value}";
        } else {
            return null;
        }
    }
    public function getVideoThumbnailAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null) {
            return url("/") . "/upload/intro_videos/thumbnails/{$value}";
        } else {
            return null;
        }
    }

}
