<?php

namespace App\Models;

use App\Models\Nc;
use App\Models\Audit;
use App\Models\Paths;
use App\Models\Fichier;
use App\Models\Section;
use App\Models\Service;
use App\Models\DossierSimple;
use App\Models\FichierDePreuve;
use App\Models\operationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class NonConformite extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'level',
        'nc_id',
        'section_id'
    ];



    public function nc()
    {
        return $this->belongsTo(Nc::class);
    }

    public function services()
    {
        return $this->morphToMany(Service::class, 'serviable');
    }

    public function fichiers()
    {
        return $this->morphMany(Fichier::class, 'parent');
    }

    public function dossiers()
    {
        return $this->morphMany(DossierSimple::class, 'parent');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
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

        static::deleting(function($fnc) { // before delete() method call this
            
            foreach ($fnc->dossiers as $key => $child_folder) $child_folder->delete();
            foreach ($fnc->fichiers as $key => $child_file) $child_file->delete();

            $fnc->services()->detach();
            $fnc->path()->delete();
            $fnc->operation()->delete();

        });
    }

}
