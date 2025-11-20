<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Video extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['video_title','uploaded_by','uploader','video_url','video_duration','video_format','tags'];

    protected $casts = [
        "uploaded_by" => "integer",
        "video_duration" => "integer",
        "tags" => "array",
        "parent_id" => "integer"
    ];

    public function getVideoFileAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null) {
            return url("/") . "/upload/videos/{$value}";
        } else {
            return null;
        }
    }
    public function getVideoThumbnailAttribute($value)
    {
        if (isset($value) && $value != "" && $value != null) {
            return url("/") . "/upload/videos/thumbnails/{$value}";
        } else {
            return null;
        }
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class,"uploaded_by","id");
    }
    public function coach()
    {
        return $this->belongsTo(Coach::class,"uploaded_by","id");
    }

    public function exercises()
    {
        return $this->hasMany(ExerciseVideo::class,"video_id","id");
    }
    public function exercises_data()
    {
        return $this->hasManyThrough(Exercise::class, ExerciseVideo::class, 'video_id', 'id', 'id', 'exercise_id');
    }

    public function upload_by()
    {
        return $this->morphTo('upload_by','uploader','uploaded_by');
    }

}
