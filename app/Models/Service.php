<?php

namespace App\Models;

use App\Http\Traits\NodeTrait;
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
    use NodeTrait;

    protected $fillable = [
        'name',
    ];




    public function sections()
    {
        return $this->morphedByMany(Section::class, 'serviable');
    }

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

    public function serviables()
    {
        $serviables = [];

//        foreach ($this->users as $user) array_push($serviables, $user);
        foreach ($this->sections as $section)
        {
            $section->path = $section->path()->get();
            array_push($serviables, $section);
        }
        foreach ($this->audits as $audit)
        {
            $audit->path = $audit->path()->get();
            array_push($serviables, $audit);
        }
        foreach ($this->checkLists as $checkList)
        {
            $checkList->path = $checkList->path()->get();
            array_push($serviables, $checkList);
        }
        foreach ($this->dps as $dp)
        {
            $dp->path = $dp->path()->get();
            array_push($serviables, $dp);
        }
        foreach ($this->nonCs as $nonC)
        {
            $nonC->path = $nonC->path()->get();
            array_push($serviables, $nonC);
        }
        foreach ($this->fncs as $fnc)
        {
            $fnc->path = $fnc->path()->get();
            array_push($serviables, $fnc);
        }
        foreach ($this->dossiers as $dossier)
        {
            $dossier->path = $dossier->path()->get();
            array_push($serviables, $dossier);
        }
        foreach ($this->fichiers as $fichier)
        {
            $fichier->path = $fichier->path()->get();
            array_push($serviables, $fichier);
        }

        return $serviables;
    }


    public static function boot() {
        parent::boot();

        static::deleting(function($service)
        { // before delete() method call this

//            $serviables = $service->serviables();
//
//            foreach ($serviables as $key => $serviable)
//            {
//                $serviable->delete();
//            };
//
//            foreach ($this->users as $user)
//            {
//                $user->services()->detach([$service->id]);
//                $user->refresh();
//
//                if ( count($user->services()->get()) ) continue;
//
//                $user->delete();
//            }

        });
    }

}
