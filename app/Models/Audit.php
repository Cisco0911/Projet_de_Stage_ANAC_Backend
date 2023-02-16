<?php

namespace App\Models;

use App\Models\Nc;
use App\Models\User;
use App\Models\Fichier;
use App\Models\Paths;
use App\Models\Section;
use App\Models\Service;
use App\Models\checkList;
use App\Models\Conformite;
use App\Models\DossierPreuve;
use App\Models\DossierSimple;
use App\Models\NonConformite;
use App\Models\FichierDePreuve;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Audit extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'section_id',
        'user_id',
    ];


    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function validator()
    {
        return $this->belongsTo(User::class, "validator_id");
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function services()
    {
        return $this->morphToMany(Service::class, 'serviable');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function nc()
    {
        return $this->hasOne(Nc::class);
    }

    public function checklist()
    {
        return $this->hasOne(checkList::class);
    }

    public function dossier_preuve()
    {
        return $this->hasOne(DossierPreuve::class);
    }

    public function fichiers()
    {
        return $this->morphMany(Fichier::class, 'parent');
    }

    public function dossiers()
    {
        return $this->morphMany(DossierSimple::class, 'parent');
    }

    public function path()
    {
        return $this->morphOne(Paths::class, 'routable');
    }

    public function operation()
    {
        return $this->morphOne(operationNotification::class, 'operable');
    }


    public static function boot() {
        parent::boot();

        static::deleting(function($audit) { // before delete() method call this

            foreach ($audit->dossiers as $key => $child_folder) $child_folder->delete();
            foreach ($audit->fichiers as $key => $child_file) $child_file->delete();

            $audit->checklist->delete();
            $audit->dossier_preuve->delete();
            $audit->nc->delete();
            $audit->users()->detach();
            $audit->services()->detach();
            $audit->path()->delete();
            $audit->operation()->delete();

        });
    }

}
