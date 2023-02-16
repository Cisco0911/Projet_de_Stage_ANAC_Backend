<?php

namespace App\Models;

use App\Models\Audit;
use App\Models\Fichier;
use App\Models\Paths;
use App\Models\Section;
use App\Models\Service;
use App\Models\DossierSimple;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class checkList extends Model
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

    public function validator()
    {
        return $this->belongsTo(User::class, "validator_id");
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

        static::deleting(function($checkList) { // before delete() method call this

            foreach ($checkList->dossiers as $key => $child_folder) $child_folder->delete();
            foreach ($checkList->fichiers as $key => $child_file) $child_file->delete();

            $checkList->services()->detach();
            $checkList->path()->delete();

        });
    }



}
