<?php

namespace App\Models;

use App\Models\Nc;
use App\Models\Fichier;
use App\Models\checkList;
use App\Models\DossierPreuve;
use App\Models\DossierSimple;
use App\Models\NonConformite;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Service extends Model
{
    use HasFactory;




    public function users()
    {
        return $this->morphedByMany(User::class, 'serviable');
    }
    
    public function audits()
    {
        return $this->morphedByMany(Audit::class, 'serviable');
    }
    
    public function checkLists()
    {
        return $this->morphedByMany(checkList::class, 'serviable');
    }
    
    public function dps()
    {
        return $this->morphedByMany(DossierPreuve::class, 'serviable');
    }
    
    public function nonCs()
    {
        return $this->morphedByMany(Nc::class, 'serviable');
    }
    
    public function fncs()
    {
        return $this->morphedByMany(NonConformite::class, 'serviable');
    }
    
    public function dossiers()
    {
        return $this->morphedByMany(DossierSimple::class, 'serviable');
    }
    
    public function fichiers()
    {
        return $this->morphedByMany(Fichier::class, 'serviable');
    }
}
