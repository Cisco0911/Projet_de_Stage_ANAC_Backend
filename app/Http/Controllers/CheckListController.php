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

    public static function find(int $id)
    {
        $checkList = checkList::find($id);
        $checkList->section;
        $checkList->services;
        $checkList->path;
        $checkList->audit;
        $checkList->dossiers;
        $checkList->fichiers;

        return $checkList;
    }
}
