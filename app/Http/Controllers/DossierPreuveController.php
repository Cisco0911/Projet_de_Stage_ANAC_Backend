<?php

namespace App\Http\Controllers;

use App\Events\NodeUpdateEvent;
use App\Http\Traits\NodeTrait;
use App\Http\Traits\ResponseTrait;
use Exception;
use Illuminate\Http\Request;
use App\Models\DossierPreuve;
use Illuminate\Support\Facades\DB;

class DossierPreuveController extends Controller
{
    //
    use NodeTrait;

    public static function format($dp)
    {
        $dp->services;
        $dp->audit;

        if ($dp->is_validated) $dp->validator = UserController::find($dp->validator_id);

        $node = json_decode($dp);

        return $node;
    }

    public function get_Dps()
    {
       $dps = DossierPreuve::all();

       foreach ($dps as $key => $dp) self::format($dp);

       return $dps;
    }

    public static function find(int $id) :DossierPreuve | null
    {
        $dp = DossierPreuve::find($id);
        if ($dp)
        {
            $dp->section;
            $dp->services;
            $dp->path;
            $dp->audit;
            $dp->dossiers;
            $dp->fichiers;

            if ($dp->is_validated) $dp->validator = UserController::find($dp->validator_id);
        }

        return $dp;
    }

    function update_dp( Request $request )
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

            $dp = DossierPreuve::find($request->id);

            if (!$dp) throw new Exception("Dossier preuve inexistant !!");

            switch ($request->update_object)
            {
                case 'is_validated':
                {
//                    throw new Exception("lalalalalaa");

                    if ( !$this->can_modify_valid_state($dp) )
                    {
                        if ($dp->is_validated)
                        {
                            if ($this->can_modify_node($dp))
                            {
                                if ( $this->ask_permission_for('modification', $dp) )
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
                        $dp = $this->valid_node($dp);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }
                    else
                    {
                        $dp = $this->unvalid_node($dp);

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

            NodeUpdateEvent::dispatch($dp->services()->get(), array_map( $getId, $are_updated ), "update");
        }
        else NodeUpdateEvent::dispatch($dp->services()->get(), [$this->get_broadcast_id($dp)], "update");

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get_success($dp);

    }
}
