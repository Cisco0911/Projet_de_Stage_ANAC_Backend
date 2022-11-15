<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\DossierPreuve;

class DossierPreuveController extends Controller
{
    //


    public function get_Dps()
    {
       $dps = DossierPreuve::all();

       foreach ($dps as $key => $dp) {
        # code...

        $dp->services;
        $dp->audit;

       }

       return $dps;
    }

    public static function find(int $id)
    {
        $dp = DossierPreuve::find($id);
        $dp->section;
        $dp->services;
        $dp->path;
        $dp->audit;
        $dp->dossiers;
        $dp->fichiers;

        return $dp;
    }
}
