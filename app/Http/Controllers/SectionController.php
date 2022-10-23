<?php

namespace App\Http\Controllers;

use App\Models\Section;
use Illuminate\Http\Request;

class SectionController extends Controller
{
    //


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
