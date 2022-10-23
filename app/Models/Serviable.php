<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Serviable extends Model
{
    use HasFactory;


    protected $fillable = [
        'service_id',
        'serviable_id',
        'serviable_type',
    ];
    
}
