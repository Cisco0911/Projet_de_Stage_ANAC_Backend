<?php

namespace App\Http\Controllers;

use App\Models\checkList;
use Illuminate\Http\Request;

class CheckListController extends Controller
{
    //


    public function get_checkLists()
    {
       $checkLists = checkList::all();

       foreach ($checkLists as $key => $checkList) {
        # code...

        $checkList->services;
        $checkList->audit;

       }

       return $checkLists;
    }
}
