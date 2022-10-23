<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Paths extends Model
{
    use HasFactory;


    protected $fillable = [
        'value',
        'routable_id',
        'routable_type',
    ];



    public function routable()
    {
        return $this->morphTo();
    }

}
