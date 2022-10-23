<?php

namespace App\Http\Controllers;

use App\Models\Nc;
use App\Models\User;
use App\Models\Paths;
use Illuminate\Http\Request;
use App\Models\NonConformite;
use App\Events\NodeUpdateEvent;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\ServiableTrait;
use Illuminate\Support\Facades\Auth;
use App\Models\operationNotification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\RemovalNotification;
use Illuminate\Support\Facades\Notification;

class NonConformiteController extends Controller
{
    //
    use ServiableTrait;

    function format($element)
    {
        $element->services;

        $node = json_decode($element);

        // switch ($element->parent_type) {
        //     case "App\Models\Audit":
        //         $node->parent_type = 'audit';
        //         break;
        //     case "App\Models\checkList":
        //         $node->parent_type = 'checkList';
        //         break;
        //     case "App\Models\DossierPreuve":
        //         $node->parent_type = 'dp';
        //         break;
        //     case "App\Models\Nc":
        //         $node->parent_type = 'nonC';
        //         break;
        //     case "App\Models\NonConformite":
        //         $node->parent_type = 'fnc';
        //         break;
        //     case "App\Models\DossierSimple":
        //         $node->parent_type = 'ds';
        //         break;
            
        //     default:
        //         $node->parent_type = '';
        //         break;
        // }

        // $node->url = "http://localhost/overview_of?id=".$element->id;

        return $node;
    }


    public function get_fncs()
    {
       $fncs = NonConformite::all();

       foreach ($fncs as $key => $fnc) {
        # code...

        $fnc->services;

       }

       return $fncs;
    }

    public function add_fncs(Request $request)
    {

        DB::beginTransaction();

        $saved = true;
        $errorResponse = null;
        $new_fncs = [];

        try {
            //code...
            $request->validate([
                'nonC_id' => ['required', 'integer'],
                'services' => ['required', 'string'],
                'debut' => ['required', 'integer'],
                'fin' => ['required', 'integer'],
                'level' => ['required', 'integer'],
            ]);

            $audit = Nc::find($request->nonC_id)->audit;

            $start = $request->debut;
            $end = $request->fin;

            for ($i= $start ; $i < $end + 1 ; $i++) { 
                # code...
                $new_fnc = NonConformite::create(
                                [
                                    'name' => 'FNC-'.$audit->name."-$i",
                                    'level' => $request->level,
                                    'nc_id' => $request->nonC_id,
                                    'section_id' => $audit->section->id,
                                    
                                ]
                            );

                $path_value = $new_fnc->nc->path->value."\\".$new_fnc->name;

                if (!Storage::exists('public\\'.$path_value)) {
                    # code...
                    $path = Paths::create(
                        [
                            'value' => $path_value,
                            'routable_id' => $new_fnc->id,
                            'routable_type' => 'App\Models\NonConformite'
                        ]
                    );
                    if (Storage::makeDirectory("public\\".$path_value)) {
            
                        $services = json_decode($request->services);

                        $this->add_to_services($services, $new_fnc->id, 'App\Models\NonConformite');

                        array_push($new_fncs, $new_fnc);
                    }
                    else {
                        $saved = false;
                        $errorResponse = ["msg" => "storingError", "value" => "Error : Creating folder not work, return false"];
                    }

                }
                else
                {
                    $saved = false;
                    $errorResponse = "existAlready";
                }
            }

            
        } 
        catch (\Throwable $th) {
            //throw $th;
            $saved = false;
            $errorResponse = ["msg" => "catchException", "value" => $th];
        }
        
        if($saved)
        {
            DB::commit(); // YES --> finalize it 

            $getId = function($element){ return $element->id.'-fnc'; };

            NodeUpdateEvent::dispatch('fnc', array_map( $getId, $new_fncs ), 'add');
        }
        else
        {
            foreach ($new_fncs as $key => $new_fnc) {
                # code...
                Storage::deleteDirectory("public\\".$new_fnc->path);
            }

            DB::rollBack(); // NO --> some error has occurred undo the whole thing
        }
        
        
        $errorResponse = $errorResponse == null ? "Something went wrong !" : $errorResponse;
        
        return $saved ? end($new_fncs) : $errorResponse ;
        
        
    }

    function del_fnc(Request $request)
    {
        
        DB::beginTransaction();

        $goesWell = true;


        try 
        {

            $target = NonConformite::find($request->id);

            $cache = $this->format($target);

            if(Auth::user()->validator_id == null)
            {

                // dd($request);

                $pathInStorage = "public\\".$target->path->value;

                $target->delete();
            }
            else 
            {
                try {
                    $new_operation = operationNotification::create(
                        [
                            'operable_id' => $cache->id,
                            'operable_type' => "App\Models\NonConformite",
                            'operation_type' => 'deletion',
                            'from_id' => Auth::user()->id,
                            'validator_id' => Auth::user()->validator_id
                        ]
                    );
                } 
                catch (\Throwable $th) {
                    return \response('en attente', 500);
                    
                }
                
                $new_operation->operable;
                $new_operation->front_type = 'fnc';
                Notification::sendNow(User::find(Auth::user()->validator_id), new RemovalNotification('Non-Conformite', $new_operation, Auth::user()));
                DB::commit();
                return 'attente';
            }

        } 
        catch (\Throwable $th) {
            //throw $th;
            $goesWell = false;
        }
        
        if($goesWell)
        {
            Storage::deleteDirectory($pathInStorage);
            DB::commit(); // YES --> finalize it 
            
            NodeUpdateEvent::dispatch('fnc', $cache, "delete");
            
            return $target;
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing
            
            return \response($th, 500);
        }

    }

    function update_fnc( Request $request )
    {
        
        DB::beginTransaction();

        $goesWell = true;


        try 
        {
            
            $request->validate([
                'id' => ['required', 'integer'],
                'update_object' => ['required', 'string'],
                'new_value' => ['required'],
            ]);

            if ($request->update_object == 'level') 
            {
                # code...
                NonConformite::where('id', $request->id)->update(['level' => $request->new_value]);
            }

        } 
        catch (\Throwable $th) {
            //throw $th;
            $goesWell = false;
        }
        
        if($goesWell)
        {
            DB::commit(); // YES --> finalize it 

            // $getId = function($element){ return $element->id.'-fnc'; }; array_map( $getId, $request )
            
            NodeUpdateEvent::dispatch('fnc', $request->id.'-fnc', "update");
            
            return 'OK';
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing
            
            return \response($th, 500);
        }

    }

}
