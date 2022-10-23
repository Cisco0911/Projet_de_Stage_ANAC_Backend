<?php

namespace App\Http\Controllers;

use App\Models\Conformite;
use Illuminate\Http\Request;

class ConformiteController extends Controller
{
    //


    public function get_cs()
    {
       $cs = Conformite::all();

       return $cs;
    }

    public function add_c(Request $request)
    {

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'audit_id' => ['required', 'string'],
        ]);

        $new_c = Conformite::create(
            [
                'name' => $request->name,
                'audit_id' => $request->audit_id,
            ]
        );

        return $new_c;
        
    }

}
