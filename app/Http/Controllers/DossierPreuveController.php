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
}
