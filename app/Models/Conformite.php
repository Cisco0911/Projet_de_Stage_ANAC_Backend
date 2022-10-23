<?php

namespace App\Models;

use App\Models\Audit;
use App\Models\FichierDePreuve;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Conformite extends Model
{
    use HasFactory;


    protected $fillable = [
        'name',
        'audit_id',
    ];



    public function audit()
    {
        return $this->belongsTo(Audit::class);
    }

    public function fichier_de_preuves()
    {
        return $this->morphMany(FichierDePreuve::class, 'parent');
    }

}
