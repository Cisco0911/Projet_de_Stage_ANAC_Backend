<?php

namespace App\Http\Controllers;

use App\Http\Traits\ResponseTrait;
use App\Models\User;
use App\Models\Paths;
use App\Models\Fichier;
use Illuminate\Http\Request;
use App\Events\NodeUpdateEvent;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\ServiableTrait;
use Illuminate\Support\Facades\Auth;
use App\Models\operationNotification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\RemovalNotification;
use Illuminate\Support\Facades\Notification;

class FichierController extends Controller
{
    //
    use ServiableTrait;
    use ResponseTrait;

    public static function find(int $id)
    {
        $file = Fichier::find($id);
        $file->section;
        $file->services;
        $file->path;
        $file->parent;
        $file->operation;
        $file->url = "http://localhost/overview_of?id=".$file->id;;

        return $file;
    }

    function format($element)
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

        $node->url = "http://localhost/overview_of?id=".$element->id;

        return $node;
    }


    public function get_fs()
    {
       $fs = Fichier::all();

       foreach ($fs as $key => $fichier) {
        # code...

        // $fichier->services;

        // $type = $fs[$key]->parent_type;

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

        $fs[$key] = $this->format($fichier);

        // $fs[$key]->url = "http://localhost/overview_of?id=".$fichier->id;

       }

       return $fs;
    }

    public function overview_of(Request $request)
    {
        $path = Fichier::find($request->id)->path->value;

        return response()->file(\storage_path("app\\public\\$path"));
    }

    public function add_files(Request $request)
    {

        DB::beginTransaction();

        $saved = true;
        $errorResponse = null;
        $failed_jobs = [];
        $added_files = [];
        $duplicated_files = [];
        $new_files = [];

        try {
            //code...

            // dd($request);

            $request->validate([
                'section_id' => ['required', 'integer'],
                'fichiers' => ['required', 'array'],
                'parent_id' => ['required', 'integer'],
                'parent_type' => ['required', 'string', 'max:255'],
                'services' => ['required', 'string'],
            ]);

            foreach ($request->fichiers as $key => $file) {
                # code...

                $double = null;

                $full_name = $file->getClientOriginalName();

                $infos = explode(".",$full_name);
                $extension = end($infos);


                $new_file = Fichier::create(
                    [
                        'section_id' => $request->section_id,
                        'name' => $full_name,
                        'size' => $file->getSize(),
                        'extension' => $extension,
                        'parent_id' => $request->parent_id,
                        'parent_type' => $request->parent_type,
                    ]
                );

                $path_value = $new_file->parent->path->value."\\".$full_name;

                $path_parts = pathinfo($path_value);

                $dir = $path_parts['dirname'];
                $filename = $path_parts['filename'];

                $original_name = $filename;

                $num_copy = 1;

                while (Storage::exists("public\\".$path_value)) {
                    # code...

                    $filename = "$original_name($num_copy)";
                    $full_name = "$filename.$extension";
                    $path_value = "$dir\\$full_name";

                    $double = $full_name;

                    $num_copy++;
                }

                if (!Storage::exists("public\\".$path_value)) {
                    # code...

                    Fichier::where('id', $new_file->id)->first()->update(['name' => $full_name]);

                    $path = Paths::create(
                        [
                            'value' => $path_value,
                            'routable_id' => $new_file->id,
                            'routable_type' => 'App\Models\Fichier'
                        ]
                    );


                    if (Storage::putFileAs("public\\".$dir, $file, $full_name)) {

                        $services = json_decode($request->services);

                        $this->add_to_services($services, $new_file->id, 'App\Models\Fichier');

                        array_push($added_files, "public\\".$path->value);
                        array_push($new_files, $new_file);
                    }
                    else {
                        $saved = false;
                        $errorResponse = ["msg" => "storingError", "value" => "Error : Creating folder not work, return false"];
                    }

                }
                else
                {
                    $saved = false;
                    array_push($failed_jobs, $full_name);
                    $errorResponse = ['msg'=> "existAlready", 'value'=> $failed_jobs];
                }

                if(!is_null($double)) array_push($duplicated_files, $double);
            }


        }
        catch (\Throwable $th) {
            //throw $th;
            $saved = false;
            $errorResponse = ["msg" => "catchException File", "value" => $th];
        }

        if($saved)
        {
            DB::commit(); // YES --> finalize it
            // $new_file->url = "http://localhost/overview_of?id=".$new_file->id;
            // $new_file->parent_type = "llllo";

            $getId = function($element){ return $element->id.'-f'; };

            NodeUpdateEvent::dispatch('f', array_map( $getId, $new_files ), 'add');
        }
        else
        {
            Storage::delete($added_files);
            DB::rollBack(); // NO --> some error has occurred undo the whole thing
        }

        // DB::endTransaction();

        $good = empty($duplicated_files) ? "ok" : ['msg' => 'duplicated', 'value' => $duplicated_files];

        $errorResponse = $errorResponse == null ? "Something went wrong !" : $errorResponse;

        return $saved ? ResponseTrait::get('success', $good) : ResponseTrait::get('error', $errorResponse) ;

    }

    function del_file(Request $request)
    {

        DB::beginTransaction();

        $goesWell = true;

        $cache = null;


        try {

            $target = Fichier::find($request->id);

            $cache = $this->format($target);

            if(Auth::user()->validator_id == null || $request->approved)
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
                            'operable_type' => "App\Models\Fichier",
                            'operation_type' => 'deletion',
                            'from_id' => Auth::user()->id,
                            'validator_id' => Auth::user()->validator_id
                        ]
                    );
                } catch (\Throwable $th) {
                    return \response(ResponseTrait::get('error', 'en attente'), 500);

                }

                $new_operation->operable;
                $new_operation->front_type = 'f';
                Notification::sendNow(User::find(Auth::user()->validator_id), new RemovalNotification('Fichier', $new_operation, Auth::user()));
                DB::commit();
                return 'attente';
            }

        } catch (\Throwable $th) {
            //throw $th;
            $goesWell = false;
        }

        if($goesWell)
        {
            Storage::delete($pathInStorage);
            DB::commit(); // YES --> finalize it
            NodeUpdateEvent::dispatch('f', $cache, 'delete');

            return ResponseTrait::get('success', $target);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return \response(ResponseTrait::get('error', $th->getMessage()), 500);
        }

    }

    function update_path($file)
    {
        $parent = $file->parent;

        $file->path->value = $parent->path->value."\\".$file->name;

        $file->push();
        $file->refresh();

        return $file->path->value;

    }

    public function move_file(Request $request)
    {

        DB::beginTransaction();

        $goes_well = true;

        try
        {
            $request->validate([
                'destination_id' => ['required', 'integer'],
                'destination_type' => ['required', 'string', 'max:255'],
                'id' => ['required', 'integer'],
            ]);

            $old_file = Fichier::find($request->id);
            $from = json_decode( json_encode( 'public\\'.$old_file->path->value ) );

//            DossierSimple::where('id', $request->id)->update(
//                [
//                    'parent_id' => $request->destination_id,
//                    'parent_type' => $request->destination_type
//                ]
//            );

            $old_file->parent_id = $request->destination_id;
            $old_file->parent_type = $request->destination_type;

            $old_file->push();

            $new_file = $old_file->refresh();

            $to = json_decode( json_encode('public\\'.$this->update_path($new_file) ) );

            $goes_well = Storage::move(
                $from,
                $to
            );
        }
        catch (\Throwable $th)
        {
            return ResponseTrait::get('error', $th->getMessage());
        }



        if($goes_well)
        {
            DB::commit(); // YES --> finalize it
            try
            {
                NodeUpdateEvent::dispatch('f', [$new_file->id.'-f'], "update");
            }
            catch (\Throwable $e)
            {}
            return ResponseTrait::get('success', $new_file);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get('error', $goes_well);
        }

    }
}
