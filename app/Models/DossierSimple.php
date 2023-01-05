<?php

namespace App\Models;

use App\Http\Controllers\UserController;
use App\Models\Paths;
use App\Models\Fichier;
use App\Models\Section;
use App\Models\Service;
use App\Models\operationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DossierSimple extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'section_id',
        'is_validated',
        'validator_id',
//        'parent_id',
//        'parent_type',
    ];



    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    public function services()
    {
        return $this->morphToMany(Service::class, 'serviable');
    }

    public function parent()
    {
        return $this->morphTo();
    }

    public function validator()
    {
        return $this->belongsTo(User::class);
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

        static::deleting(function($folder) { // before delete() method call this

            foreach ($folder->dossiers as $key => $child_folder) $child_folder->delete();
            foreach ($folder->fichiers as $key => $child_file) $child_file->delete();

            $folder->services()->detach();
            $folder->path()->delete();
            $folder->operation()->delete();

        });
    }

}
