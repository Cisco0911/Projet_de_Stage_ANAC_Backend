<?php

namespace App\Models;

use App\Models\Paths;
use App\Models\Section;
use App\Models\Service;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 *
 */
class Fichier extends Model
{
    use HasFactory;


    /**
     * @var string[]
     */
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


    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function services()
    {
        return $this->morphToMany(Service::class, 'serviable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphTo
     */
    public function parent()
    {
        return $this->morphTo();
    }

    public function validator()
    {
        return $this->belongsTo(User::class, "validator_id");
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function path()
    {
        return $this->morphOne(Paths::class, 'routable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function operation()
    {
        return $this->morphOne(operationNotification::class, 'operable');
    }


    /**
     * @return void
     */
    public static function boot() {
        parent::boot();

        static::deleting(function($file) { // before delete() method call this

            $file->services()->detach();
            $file->path()->delete();
            $file->operation()->delete();

        });
    }


}
