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
use Thomasjohnkane\Snooze\ScheduledNotification;
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

    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activities_history::class);
    }

    public function audits_belonging_to(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Audit::class);
    }

    public function services(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(Service::class, 'serviable');
    }

    public function validated_audits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Audit::class, "validator_id");
    }

    public function validated_checkLists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(checkList::class, "validator_id");
    }

    public function validated_dps(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DossierPreuve::class, "validator_id");
    }

    public function validated_ncs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Nc::class, "validator_id");
    }

    public function validated_fncs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(NonConformite::class, "validator_id");
    }

    public function validated_folders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(DossierSimple::class, "validator_id");
    }

    public function validated_files(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Fichier::class, "validator_id");
    }

    public function validated_nodes()
    {
        $nodes = [];

        foreach ($this->validated_audits()->get() as $validated_audit) array_push($nodes, $validated_audit);
        foreach ($this->validated_checkLists()->get() as $validated_checkList) array_push($nodes, $validated_checkList);
        foreach ($this->validated_dps()->get() as $validated_dp) array_push($nodes, $validated_dp);
        foreach ($this->validated_ncs()->get() as $validated_nc) array_push($nodes, $validated_nc);
        foreach ($this->validated_fncs()->get() as $validated_fnc) array_push($nodes, $validated_fnc);
        foreach ($this->validated_folders()->get() as $validated_folder) array_push($nodes, $validated_folder);
        foreach ($this->validated_files()->get() as $validated_file) array_push($nodes, $validated_file);

        return $nodes;
    }


    public static function boot() {
        parent::boot();

        static::deleting(function($user)
        { // before delete() method call this
            if ( count( $user->audits()->get() ) ) throw new \Exception("L'utilisateur {$user->name} est RA d'un audit, veuillez faire une transmission de rôle d'abord !!");

            $user->audits_belonging_to()->detach();

            if ( count( $user->validated_nodes() ) ) throw new \Exception("L'utilisateur {$user->name} a validé un fichier, veuillez faire une transmission de rôle d'abord !!");

            ScheduledNotification::cancelByTarget($user);

            Notification::where('type', 'App\Notifications\NewUserNotification')
                ->where('data->user_id', $user->id)
                ->delete();

        });
    }

}
