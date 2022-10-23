<?php

namespace App\Models;

use App\Models\Audit;
use App\Models\Paths;
use App\Models\Fichier;
use App\Models\Section;
use App\Models\Service;
use App\Models\DossierSimple;
use App\Models\NonConformite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Nc extends Model
{
    use HasFactory;


    protected $fillable = [
        'audit_id',
        'section_id'
    ];



    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }
    
    public function fncs()
    {
        return $this->hasMany(NonConformite::class);
    }

    public function services()
    {
        return $this->morphToMany(Service::class, 'serviable');
    }

    public function section()
    {
        return $this->belongsTo(Section::class);
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


    public static function boot() {
        parent::boot();

        static::deleting(function($nonC) { // before delete() method call this
            
            foreach ($nonC->dossiers as $key => $child_folder) $child_folder->delete();
            foreach ($nonC->fichiers as $key => $child_file) $child_file->delete();
            foreach ($nonC->fncs as $key => $fnc) $fnc->delete();

            $nonC->services()->detach();
            $nonC->path()->delete();

        });
    }

}
