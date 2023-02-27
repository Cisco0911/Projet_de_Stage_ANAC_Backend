<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Activities_history extends Model
{
    use HasFactory;


    protected $fillable = [
        "id",
        "user_inspector_number",
        "user_name",
        "target_id",
        "target_type",
        "target_name",
        "operation",
        "services",
    ];

    public $incrementing = false;
    protected $keyType = 'string';


    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
