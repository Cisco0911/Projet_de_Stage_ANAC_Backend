<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Audit;
use App\Models\Service;
use App\Models\NonConformite;
use Laravel\Sanctum\HasApiTokens;
use App\Models\operationNotification;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Thomasjohnkane\Snooze\Traits\SnoozeNotifiable;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SnoozeNotifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'second_name',
        'name',
        'email',
        'inspector_number',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function receivesBroadcastNotificationsOn()
    {
        return 'user.'.$this->id;
    }



    public function audits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Audit::class);
    }

    public function audits_belonging_to(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Audit::class);
    }

    public function fncs(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(NonConformite::class, Audit::class);
    }

    public function services(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(Service::class, 'serviable');
    }

    public function validated_folders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DossierSimple::class);
    }

//    public function operationNotifications(): \Illuminate\Database\Eloquent\Relations\HasMany
//    {
//        return $this->hasMany(operationNotification::class, 'validator_id');
//    }
//
//    public function operationInQueue(): \Illuminate\Database\Eloquent\Relations\HasMany
//    {
//        return $this->hasMany(operationNotification::class, 'from_id');
//    }

}
