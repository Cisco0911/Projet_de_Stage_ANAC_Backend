<?php

namespace App\Http\Controllers;

use App\Events\NodeUpdateEvent;
use App\Http\Traits\NodeTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\checkList;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckListController extends Controller
{
    //

    use NodeTrait;

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

    function update_checkList( Request $request )
    {

        DB::beginTransaction();

        $goesWell = true;
        $GLOBALS['to_broadcast'] = [];

        try
        {

            $request->validate([
                'id' => ['required', 'integer'],
                'update_object' => ['required', 'string'],
                'new_value' => ['required'],
            ]);

            $checkList = checkList::find($request->id);

            switch ($request->update_object)
            {
                case 'is_validated':
                {

                    if ( !$this->can_modify_valid_state($checkList) )
                    {
                        if ($checkList->is_validated)
                        {
                            if ($this->can_modify_node($checkList))
                            {
                                if ( $this->ask_permission_for('modification', $checkList) )
                                {
                                    $GLOBALS['to_broadcast'] = [];

                                    DB::commit();

                                    return ResponseTrait::get_info("Demande de permission envoyé");
                                }
                                else
                                {
                                    $GLOBALS['to_broadcast'] = [];

                                    DB::rollBack();

                                    throw new Exception("Demande existant");
                                }

                            }
                            else throw new Exception("Vous n'avez pas les droits nécessaires", -2);
                        }
                        else throw new Exception("Vous n'avez pas les droits nécessaires", -2);
                    }

                    if ($request->new_value)
                    {
                        $checkList = $this->valid_node($checkList);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }
                    else
                    {
                        $checkList = $this->unvalid_node($checkList);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }

                    break;
                }
                default:
                    DB::rollBack();

                    $GLOBALS['to_broadcast'] = [];

                    return ResponseTrait::get('success', 'Nothing was done');
            }

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            $GLOBALS['to_broadcast'] = [];

            return ResponseTrait::get_error($th);
        }


        DB::commit(); // YES --> finalize it

        // $getId = function($element){ return $element->id.'-fnc'; }; array_map( $getId, $request )

        if (!empty($are_updated))
        {
            $getId = function($element){ return $this->get_broadcast_id($element); };

            NodeUpdateEvent::dispatch('checkList', array_map( $getId, $are_updated ), "update");
        }
        else NodeUpdateEvent::dispatch('checkList', [$this->get_broadcast_id($checkList)], "update");

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get_success($checkList);

    }
}
