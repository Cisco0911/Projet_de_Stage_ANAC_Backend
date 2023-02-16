<?php

namespace App\Models;

use App\Models\Paths;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Section extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];



    public function services()
    {
        return $this->morphToMany(Service::class, 'serviable');
    }

    public function path()
    {
        return $this->morphOne(Paths::class, 'routable');
    }

    public function audits()
    {
        return $this->hasMany(Audit::class);
    }

    public function fichiers()
    {
        return $this->morphMany(Fichier::class, 'parent');
    }

    public function dossiers()
    {
        return $this->morphMany(DossierSimple::class, 'parent');
    }


    public static function boot() {
        parent::boot();

        static::deleting(function($section) { // before delete() method call this

            foreach ($section->audits as $key => $audit) $audit->delete();
            foreach ($section->dossiers as $key => $child_folder) $child_folder->delete();
            foreach ($section->fichiers as $key => $child_file) $child_file->delete();

            $section->services()->detach();
            $section->path()->delete();

        });
    }

}
