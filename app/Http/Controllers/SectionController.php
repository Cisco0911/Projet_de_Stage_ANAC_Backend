<?php

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    //

    public static function find(int $id)
    {
        $section = Section::find($id);
        $section->services;
        $section->path;
        $section->audit;
        $section->dossiers;
        $section->fichiers;


        return $section;
    }


    public function get_ss()
    {
       $ss = Section::all();

       foreach ($ss as $key => $s) {
        # code...

        $s->services;

       }

       return $ss;
    }

}
