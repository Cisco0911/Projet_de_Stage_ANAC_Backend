<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\FichierDePreuve;

class FichierDePreuveController extends Controller
{
    //



    public function get_fdps()
    {
       $fdps = FichierDePreuve::all();

       foreach ($fdps as $key => $fdp) {
        # code...

        $type = $fdps[$key]->parent_type;

        $type = $type == "App\Models\Conformite" ? 'c' : 'nc';

        $fdps[$key]->parent_type = $type;

       }

       return $fdps;
    }


    public function add_fdps(Request $request)
    {
        
        $request->validate([
            'parent_id' => ['required', 'string', 'max:255'],
            'parent_type' => ['required', 'string', 'max:255'],
        ]);

        $new_fdp = FichierDePreuve::create(
            [
                'name' => $request->name.".$request->extension",
                'extension' => $request->extension,
                'path' => "path",
                'parent_id' => $request->parent_id,
                'parent_type' => $request->parent_type,
            ]
        );

        return $new_fdp;
        
    }


}
