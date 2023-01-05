<?php

namespace App\Models;

use App\Models\Paths;
use App\Models\Section;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Fichier extends Model
{
    use HasFactory;


    protected $fillable = [
        'section_id',
        'name',
        'extension',
        'size',
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

        static::deleting(function($file) { // before delete() method call this

            $file->services()->detach();
            $file->path()->delete();
            $file->operation()->delete();

        });
    }


}
