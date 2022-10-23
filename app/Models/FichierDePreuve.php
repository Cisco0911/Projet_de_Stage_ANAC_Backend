<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FichierDePreuve extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'extension',
        'path',
        'parent_id',
        'parent_type',
    ];



    public function parent()
    {
        return $this->morphTo();
    }

}
