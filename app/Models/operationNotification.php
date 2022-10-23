<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class operationNotification extends Model
{
    use HasFactory;


    protected $fillable = [
        'operable_id',
        'operable_type',
        'from_id',
        'validator_id',
        'operation_type',
    ];

    public function operable()
    {
        return $this->morphTo();
    }

    public function validator()
    {
        return $this->belongsTo(User::class);
    }

    public function from()
    {
        return $this->belongsTo(User::class);
    }
}
