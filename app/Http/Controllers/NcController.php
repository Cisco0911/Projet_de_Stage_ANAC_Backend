<?php

namespace App\Http\Controllers;

use App\Models\Nc;
use Illuminate\Http\Request;

class NcController extends Controller
{
    //


    public function get_NonCs()
    {
       $nonCs = Nc::all();

       foreach ($nonCs as $key => $nonC) {
        # code...

        $nonC->services;
        $nonC->audit;

       }

       return $nonCs;
    }

    public static function find(int $id)
    {
        $nonC = Nc::find($id);
        $nonC->section;
        $nonC->services;
        $nonC->path;
        $nonC->audit;
        $nonC->dossiers;
        $nonC->fichiers;

        return $nonC;
    }
}
