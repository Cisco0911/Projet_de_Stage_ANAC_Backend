<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdministratorController extends Controller
{
    //

    protected function format($element)
    {
        if ($element instanceof User)
        {
            $element->services;

            return $element;
        }
    }


    public function get_data(Request $request)
    {

        $data = new \stdClass();

        $data->auth = Auth::user();

        $data->sections = Section::all();

        $data->services = Service::all();

        $users = User::where("id", "!=", 0)->get();
        foreach ($users as $key => $user) $users[$key] = $this->format($user);
        $data->users = $users;

        return $data;
    }

}
