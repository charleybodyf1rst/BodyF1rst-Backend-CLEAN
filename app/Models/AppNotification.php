<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AppNotification extends Model
{
    use HasFactory,SoftDeletes;

    protected $fillable = ['user_id','model_id','model_type','title','message','api_response','cta_link','user_type','schedule_type','redirect_url','metadata'];

    protected $hidden = ["api_response"];

    protected $casts = [
        "user_id" => "integer",
        "model_id" => "integer",
        "metadata" => "array",
    ];

    public function readAt()
    {
        return $this->hasMany(AppNotificationRead::class,'notification_id');
    }
    public function user()
    {
        return $this->belongsTo(User::class,"user_id");
    }
    public function users()
    {
        return $this->hasManyThrough(User::class, NotificationUser::class, 'notification_id', 'id', 'id', 'user_id');
    }
    public function user_pivots()
    {
        return $this->belongsToMany(User::class, 'notification_users', 'notification_id', 'user_id')->withTimestamps();
    }
    public function organizations()
    {
        return $this->hasManyThrough(Organization::class, NotificationUser::class, 'notification_id', 'id', 'id', 'organization_id');
    }
    public function organization_pivots()
    {
        return $this->belongsToMany(Organization::class, 'notification_users', 'notification_id', 'organization_id')->withTimestamps();
    }

    public function notify_by()
    {
        return $this->morphTo('notify_by','model_id','model_type');
    }
}
