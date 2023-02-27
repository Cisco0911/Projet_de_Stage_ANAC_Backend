<?php

namespace App\Http\Controllers;

use App\Events\NodeUpdateEvent;
use App\Http\Traits\NodeTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\Nc;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NcController extends Controller
{
    //
    use NodeTrait;

    public static function format($nonC)
    {
        $nonC->services;
        $nonC->audit;

        if ($nonC->is_validated) $nonC->validator = UserController::find($nonC->validator_id);

        $node = json_decode($nonC);

        return $node;
    }

    public function get_NonCs()
    {
       $nonCs = Nc::all();

       foreach ($nonCs as $key => $nonC) self::format($nonC);

       return $nonCs;
    }

    public static function find(int $id) :Nc | null
    {
        $nonC = Nc::find($id);
        if ($nonC)
        {
            $nonC->section;
            $nonC->services;
            $nonC->path;
            $nonC->audit;
            $nonC->dossiers;
            $nonC->fichiers;

            if ($nonC->is_validated) $nonC->validator = UserController::find($nonC->validator_id);
        }

        return $nonC;
    }

    function update_nc( Request $request )
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

            $nonC = Nc::find($request->id);

            if (!$nonC) throw new Exception("Dossier Nc inexistant !!");

            switch ($request->update_object)
            {
                case 'is_validated':
                {

                    if ( !$this->can_modify_valid_state($nonC) )
                    {
                        if ($nonC->is_validated)
                        {
                            if ($this->can_modify_node($nonC))
                            {
                                if ( $this->ask_permission_for('modification', $nonC) )
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
                        $nonC = $this->valid_node($nonC);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }
                    else
                    {
                        $nonC = $this->unvalid_node($nonC);

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

            NodeUpdateEvent::dispatch($nonC->services()->get(), array_map( $getId, $are_updated ), "update");
        }
        else NodeUpdateEvent::dispatch($nonC->services()->get(), [$this->get_broadcast_id($nonC)], "update");

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get_success($nonC);

    }
}
