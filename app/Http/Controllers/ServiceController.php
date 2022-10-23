<?php

namespace App\Http\Controllers;

use App\Models\Service;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    //


    public function get_services()
    {
       $services = Service::all();

       return $services;
    }

    public function add_service(Request $request)
    {

        $request->validate([
            'name' => ['required', 'string', 'max:10'],
        ]);

        $new_service = Service::create(
            [
                'name' => $request->name,
                'description' => $request->description == "" ? "Aucune description" : $request->description,
            ]
        );

        return $new_c;
        
    }

}
