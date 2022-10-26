<?php

namespace App\Http\Controllers;

use Exception;
use App\Models\User;
use App\Models\Paths;
use App\Models\Serviable;
use App\Events\RemovalEvent;
use Illuminate\Http\Request;
use App\Models\DossierSimple;
use App\Events\NodeUpdateEvent;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\ServiableTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\operationNotification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\RemovalNotification;
use Illuminate\Support\Facades\Notification;

class DossierSimpleController extends Controller
{
    //
    use ServiableTrait;

    private function format($element)
    {
        $element->services;

        $node = json_decode($element);

        switch ($element->parent_type) {
            case "App\Models\Audit":
                $node->parent_type = 'audit';
                break;
            case "App\Models\checkList":
                $node->parent_type = 'checkList';
                break;
            case "App\Models\DossierPreuve":
                $node->parent_type = 'dp';
                break;
            case "App\Models\Nc":
                $node->parent_type = 'nonC';
                break;
            case "App\Models\NonConformite":
                $node->parent_type = 'fnc';
                break;
            case "App\Models\DossierSimple":
                $node->parent_type = 'ds';
                break;
            
            default:
                $node->parent_type = '';
                break;
        }

        return $node;
    }

    public function get_ds()
    {
       $ds = DossierSimple::all();

       foreach ($ds as $key => $dossier) {
        # code...

        // $dossier->services;

        // $type = $ds[$key]->parent_type;

        // switch ($type) {
        //     case "App\Models\Audit":
        //         $type = 'audit';
        //         break;
        //     case "App\Models\checkList":
        //         $type = 'checkList';
        //         break;
        //     case "App\Models\DossierPreuve":
        //         $type = 'dp';
        //         break;
        //     case "App\Models\Nc":
        //         $type = 'nonC';
        //         break;
        //     case "App\Models\NonConformite":
        //         $type = 'fnc';
        //         break;
        //     case "App\Models\DossierSimple":
        //         $type = 'ds';
        //         break;
            
        //     default:
        //         $type = '';
        //         break;
        // }

        $ds[$key] = $this->format($dossier);

       }

       return $ds;
    }


    public function add_folder(Request $request)
    {
        
        DB::beginTransaction();

        $saved = true;
        $errorResponse = null;

        try {
            //code...
            
            $request->validate([
                'section_id' => ['required', 'integer'],
                'name' => ['required', 'string', 'max:255'],
                'parent_id' => ['required', 'integer'],
                'parent_type' => ['required', 'string', 'max:255'],
                'services' => ['required', 'string'],
            ]);

            $new_folder = DossierSimple::create(
                [
                    'section_id' => $request->section_id,
                    'name' => $request->name,
                    'parent_id' => $request->parent_id,
                    'parent_type' => $request->parent_type,
                ]
            );

            $path_value = $new_folder->parent->path->value."\\".$new_folder->name;

            if (!Storage::exists('public\\'.$path_value)) {
                # code...
                $path = Paths::create(
                    [
                        'value' => $path_value,
                        'routable_id' => $new_folder->id,
                        'routable_type' => 'App\Models\DossierSimple'
                    ]
                );
                if (Storage::makeDirectory("public\\".$path_value)) {
        
                    $services = json_decode($request->services);

                    $this->add_to_services($services, $new_folder->id, 'App\Models\DossierSimple');
        
                    // foreach($services as $service)
                    // {
                    //     Serviable::create(
                    //         [
                    //             'service_id' => $service->value,
                    //             'serviable_id' => $new_folder->id,
                    //             'serviable_type' => 'App\Models\DossierSimple',
                    //         ]
                    //     );
                    // }
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
        catch (\Throwable $th) {
            //throw $th;
            $saved = false;
            $errorResponse = ["msg" => "catchException", "value" => $th];
        }
        
        if($saved)
        {
            DB::commit(); // YES --> finalize it 
            NodeUpdateEvent::dispatch('ds', [$new_folder->id.'-ds'], "add");
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing
        }

        // DB::endTransaction();
        
        $errorResponse = $errorResponse == null ? "Something went wrong !" : $errorResponse;
        
        return $saved ? $new_folder : $errorResponse ;
        
    }
    

    public function del_folder(Request $request)
    {
        
        DB::beginTransaction();

        $goesWell = true;

        $cache = null;


        try 
        {

            $target = DossierSimple::find($request->id);

            $cache = $this->format($target);

            if(Auth::user()->validator_id == null || $request->approved)
            {

                // dd($request);

                $pathInStorage = "public\\".$target->path->value;

                // $target->path->delete();

                // $this->del_from_services($target->services, $target->id, 'App\Models\DossierSimple');

                $target->delete();
            }
            else 
            {
                try {
                    $new_operation = operationNotification::create(
                        [
                            'operable_id' => $cache->id,
                            'operable_type' => "App\Models\DossierSimple",
                            'operation_type' => 'deletion',
                            'from_id' => Auth::user()->id,
                            'validator_id' => Auth::user()->validator_id
                        ]
                    );
                } catch (\Throwable $th) {
                    return \response('en attente', 500);
                    
                }
                
                $new_operation->operable;
                $new_operation->front_type = 'ds';
                Notification::sendNow(User::find(Auth::user()->validator_id), new RemovalNotification('Dossier', $new_operation, Auth::user()));
                DB::commit();
                return 'attente';
            }
            // RemovalEvent::dispatch('Dossier', $cache, Auth::user());

        } 
        catch (\Throwable $th) 
        {
            //throw $th;
            $goesWell = false;
        }
        
        if($goesWell)
        {
            Storage::deleteDirectory($pathInStorage);
            DB::commit(); // YES --> finalize it 
            NodeUpdateEvent::dispatch('ds', $cache, "delete");
            
            return $target;
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing
            
            return \response($th, 500);
        }

    }

    public function test()
    {
        return 'test Réussi';
    }

}
