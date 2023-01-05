<?php

namespace App\Http\Controllers;

use App\Http\Traits\NodeTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\User;
use App\Models\Paths;
use App\Models\Fichier;
use Exception;
use Illuminate\Http\Request;
use App\Events\NodeUpdateEvent;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\ServiableTrait;
use Illuminate\Support\Facades\Auth;
use App\Models\operationNotification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\RemovalNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

class FichierController extends Controller
{
    //
    use ServiableTrait;
    use ResponseTrait;
    use NodeTrait;

    public static function find(int $id)
    {
        $file = Fichier::find($id);

        if ($file)
        {
            $file->section;
            $file->services;
            $file->path;
            $file->parent;
            $file->operation;
            $file->url = URL::signedRoute("overview.file", ["id" => $file->id]);
        }

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

        $node->url = URL::signedRoute("overview.file", ["id" => $element->id]);
//        "/overview_of/{$element->id}"

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

    public function overview_of($id)
    {
        $path = Fichier::find($id)->path->value;

        return response()->file(\storage_path("app\\public\\$path"));
    }

    public function add_files(Request $request)
    {

        DB::beginTransaction();

        $saved = true;
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

            $parent = $this->find_node($request->parent_id, $request->parent_type);

            if (empty($parent))
            {
                throw new Exception("Parent inexistant.", -4);
            }

            $feasible = $this->can_modify_node($parent);

            if( $feasible != 2 ) throw new Exception("Vous n'avez pas les droits nécessaires\nSi le parent est validé, veuillez faire une demande d'autorisation de modification", -3);

            foreach ($request->fichiers as $key => $file) {
                # code...

                $double = null;

                $full_name = $file->getClientOriginalName();

                $infos = explode(".",$full_name);
                $extension = end($infos);


                $path_value = $parent->path->value."\\".$full_name;

                $path_parts = pathinfo($path_value);

                $dir = $path_parts['dirname'];
                $filename = $path_parts['filename'];

                $original_name = $filename;

                $num_copy = 1;

                while ( Paths::where([ 'value' => $path_value ])->exists() ) {
                    # code...

                    $filename = $num_copy == 1 ? "$original_name - Copie" : "$original_name - Copie ($num_copy)";
                    $full_name = "$filename.$extension";
                    $path_value = "$dir\\$full_name";

                    $double = $full_name;

                    $num_copy++;
                }

                if ( Storage::exists("public\\".$path_value) ) Storage::delete("public\\".$path_value);

                if (!Storage::exists("public\\".$path_value)) {
                    # code...

                    $new_file = $parent->fichiers()->create(
                        [
                            'section_id' => $request->section_id,
                            'name' => $full_name,
                            'size' => $file->getSize(),
                            'extension' => $extension,
                            'is_validated' => $parent->is_validated ?? 0,
                            'validator_id' => $parent->validator_id,
                        ]
                    );

                    $path = $new_file->path()->create(
                        [
                            'value' => $path_value,
                        ]
                    );


                    if (Storage::putFileAs("public\\".$dir, $file, $full_name)) {

                        $services = json_decode($request->services);

                        $this->add_to_services($services, $new_file->id, 'App\Models\Fichier');

                        array_push($added_files, "public\\".$path->value);
                        array_push($new_files, $new_file);
                    }
                    else
                    {
                        throw new Exception('Erreur de stockage: Le stockage du fichier a échoué.', 1);
                    }

                }
                else throw new Exception("L'ajout du(des) fichier(s) a échoué", -100);

                if(!is_null($double)) array_push($duplicated_files, $double);
            }


        }
        catch (\Throwable $th)
        {
            Storage::delete($added_files);
            DB::rollBack();
            return ResponseTrait::get_error($th);
        }

        if($saved && empty($duplicated_files))
        {
            DB::commit(); // YES --> finalize it
            // $new_file->url = "http://localhost/overview_of?id=".$new_file->id;
            // $new_file->parent_type = "llllo";

            $getId = function($element){ return $element->id.'-f'; };

            NodeUpdateEvent::dispatch('f', array_map( $getId, $new_files ), 'add');

            $good = "ok";

            return ResponseTrait::get('success', $good);
        }
        elseif ($saved && !empty($duplicated_files))
        {
            DB::commit(); // YES --> finalize it
            // $new_file->url = "http://localhost/overview_of?id=".$new_file->id;
            // $new_file->parent_type = "llllo";

            $getId = function($element){ return $element->id.'-f'; };

            NodeUpdateEvent::dispatch('f', array_map( $getId, $new_files ), 'add');

            $good = ['msg' => 'duplicated', 'list' => $duplicated_files];

            return ResponseTrait::get('success', $good);
        }

        // DB::endTransaction();

    }

    public function del_file(Request $request)
    {

        DB::beginTransaction();

        $goesWell = true;

        $cache = null;


        try {

            $target = Fichier::find($request->id);

            $cache = $this->format($target);

            $feasible = $this->can_modify_node($target);

            if($feasible)
            {
                if ($feasible == 2)
                {
                    // dd($request);

                    $pathInStorage = "public\\".$target->path->value;

                    $target->delete();
                }
                else
                {
                    $this->ask_permission_for('deletion', $target);

                    DB::commit();

                    return ResponseTrait::get_info("Demande de permission envoyé");
                }
            }
            else
            {
                throw new Exception("Vous n'avez pas les droits nécessaires");
            }

        }
        catch (\Throwable $th) {
            //throw $th;
            $goesWell = false;
        }

        if($goesWell)
        {
            Storage::delete($pathInStorage);
            DB::commit(); // YES --> finalize it

            $info = json_decode('{}');
            $info->id = $cache->id; $info->type = 'f';

            NodeUpdateEvent::dispatch('f', $info, 'delete');

            return ResponseTrait::get('success', $target);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get('error', $th->getMessage());
        }

    }

    function update_file( Request $request )
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

            $file = Fichier::find($request->id);

            switch ($request->update_object)
            {
                case 'is_validated':
                {

                    if ( !$this->can_modify_valid_state($file) )
                    {
                        if ($file->is_validated)
                        {
                            if ($this->can_modify_node($file))
                            {
                                if ( $this->ask_permission_for('modification', $file) )
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
                        $file = $this->valid_node($file);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }
                    else
                    {
                        $file = $this->unvalid_node($file);

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

            NodeUpdateEvent::dispatch('f', array_map( $getId, $are_updated ), "update");
        }
        else NodeUpdateEvent::dispatch('f', [$this->get_broadcast_id($file)], "update");

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get_success($file);

    }

    public function update_path($file)
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

        try
        {
            $request->validate([
                'destination_id' => ['required', 'integer'],
                'destination_type' => ['required', 'string', 'max:255'],
                'id' => ['required', 'integer'],
            ]);

            $new_parent = $this->find_node($request->destination_id, $request->destination_type);
            $old_file = Fichier::find($request->id);

            if (empty($new_parent))
            {
                throw new Exception("Parent inexistant.", -4);
            }

            $feasible = $this->can_modify_node($new_parent);

            if( ($feasible != 2) || !$this->can_modify_node_deep_check($old_file) ) throw new Exception("Vous n'avez pas les droits nécessaires\nSi le parent ou le fichier est validé, veuillez faire une demande d'autorisation de modification", -3);

            $from = json_decode( json_encode( 'public\\'.$old_file->path->value ) );

            $destination = $new_parent;

//            DossierSimple::where('id', $request->id)->update(
//                [
//                    'parent_id' => $request->destination_id,
//                    'parent_type' => $request->destination_type
//                ]
//            );

            if ( Paths::where([ 'value' => $destination->path->value.'\\'.$old_file->name ])->exists() )
            {
                switch ((int)$request->on_exist)
                {
                    case 1:
                    {
                        $new_file = Fichier::where(
                            [
                                'name' => $old_file->name,
                                'parent_id' => (int)$request->destination_id,
                                'parent_type' => $request->destination_type,
                            ]
                        )->first();

                        if($new_file->exists()) $goes_well = true;

                        break;
                    }
                    case 2:
                    {
                        $num_copy = 1;
                        $new_name = $old_file->name;

                        while (Paths::where([ 'value' => $destination->path->value.'\\'.$new_name ])->exists()) {
                            # code...

                            $set_num = $num_copy == 1 ? "" : " ($num_copy)";

                            $new_name = $old_file->name." - Copie$set_num";

                            $num_copy++;
                        }

                        $old_file->name = $new_name;

                        $old_file->parent_id = $request->destination_id;
                        $old_file->parent_type = $request->destination_type;

                        $old_file->push();

                        $new_file = $old_file->refresh();

                        $to = json_decode( json_encode('public\\'.$this->update_path($new_file) ) );

                        $goes_well = Storage::move(
                            $from,
                            $to
                        );

                        $new_file->refresh();

                        break;
                    }
                    case 3:
                    {
                        $path = Paths::where([ 'value' => $destination->path->value.'\\'.$old_file->name ])->first();

                        $existant_file = $path->routable;

                        if (!$this->can_modify_node_deep_check($existant_file)) throw new Exception("Le fichier à la destination est validé");

                        $this->del_file(
                            new Request(
                                [
                                    'id' => $existant_file->id,
                                ]
                            )
                        );

                        $old_file->parent_id = $request->destination_id;
                        $old_file->parent_type = $request->destination_type;

                        $old_file->push();

                        $new_file = $old_file->refresh();

                        $to = json_decode( json_encode('public\\'.$this->update_path($new_file) ) );

                        $goes_well = Storage::move(
                            $from,
                            $to
                        );

                        $new_file->refresh();

                        break;
                    }
                }

            }
            else
            {
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

            $new_file->services()->detach();
            foreach ($destination->services as $service)
            {
                $new_file->services()->attach($service->id);
            }
        }
        catch (\Throwable $th)
        {
            DB::rollBack();

            return ResponseTrait::get_error($th);
        }


        DB::commit(); // YES --> finalize it
        try
        {
            NodeUpdateEvent::dispatch('f', [$new_file->id.'-f'], "update");
        }
        catch (\Throwable $e)
        {}
        return ResponseTrait::get('success', $new_file);

    }

    public function copy_file(Request $request)
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

            $new_parent = $this->find_node($request->destination_id, $request->destination_type);

            if (empty($new_parent))
            {
                throw new Exception("Parent inexistant.", -4);
            }

            $feasible = $this->can_modify_node($new_parent);

            if( $feasible != 2 ) throw new Exception("Vous n'avez pas les droits nécessaires\nSi le parent est validé, veuillez faire une demande d'autorisation de modification", -3);

            $old_file = Fichier::find($request->id);
            $from = json_decode( json_encode( 'public\\'.$old_file->path->value ) );

            $destination = $new_parent;


            if ( Paths::where([ 'value' => $destination->path->value.'\\'.$old_file->name ])->exists() )
            {
                switch ((int)$request->on_exist)
                {
                    case 1:
                    {
                        $new_file = $destination->fichiers()->where(
                            [
                                'name' => $old_file->name,
                            ]
                        )->first();

                        if($new_file->exists()) $goes_well = true;

                        break;
                    }
                    case 2:
                    {
                        $num_copy = 1;
                        $new_name = $old_file->name;

                        while (Paths::where([ 'value' => $destination->path->value.'\\'.$new_name ])->exists()) {
                            # code...

                            $set_num = $num_copy == 1 ? "" : " ($num_copy)";

                            $new_name = $old_file->name." - Copie$set_num";

                            $num_copy++;
                        }

                        $new_file = new Fichier(
                            [
                                'name' => $new_name,
                                'size' => $old_file->size,
                                'extension' => $old_file->extension,
                                'section_id' => $destination->section_id ?? $destination->id,
                            ]
                        );

                        $destination->fichiers()->save($new_file);
                        $destination->refresh();

                        $new_file->path()->create(
                            [
                                'value' => $new_file->parent->path->value.'\\'.$new_file->name
                            ]
                        );
                        foreach ($destination->services as $service)
                        {
                            $new_file->services()->attach($service->id);
                        }

                        $new_file = $new_file->refresh();

                        $to = json_decode( json_encode('public\\'.$new_file->path->value ) );

                        $goes_well = Storage::copy(
                            $from,
                            $to
                        );

                        $new_file->refresh();

                        break;
                    }
                    case 3:
                    {
                        $path = Paths::where([ 'value' => $destination->path->value.'\\'.$old_file->name ])->first();

                        $existant_file = $path->routable;

                        if (!$this->can_modify_node_deep_check($existant_file)) throw new Exception("Le fichier existant à la destination est validé");

                        $this->del_file(
                            new Request(
                                [
                                    'id' => $existant_file->id,
                                ]
                            )
                        );

                        $new_file = new Fichier(
                            [
                                'name' => $old_file->name,
                                'size' => $old_file->size,
                                'extension' => $old_file->extension,
                                'section_id' => $destination->section_id ?? $destination->id,
                            ]
                        );

                        $destination->fichiers()->save($new_file);
                        $destination->refresh();

                        $new_file->path()->create(
                            [
                                'value' => $new_file->parent->path->value.'\\'.$new_file->name
                            ]
                        );
                        foreach ($destination->services as $service)
                        {
                            $new_file->services()->attach($service->id);
                        }

                        $new_file = $new_file->refresh();

                        $to = json_decode( json_encode('public\\'.$new_file->path->value ) );

                        $goes_well = Storage::copy(
                            $from,
                            $to
                        );

                        $new_file->refresh();

                        break;
                    }
                }

            }
            else
            {
                $new_file = new Fichier(
                    [
                        'name' => $old_file->name,
                        'size' => $old_file->size,
                        'extension' => $old_file->extension,
                        'section_id' => $destination->section_id ?? $destination->id,
                    ]
                );

                $destination->fichiers()->save($new_file);
                $destination->refresh();

                $new_file->path()->create(
                    [
                        'value' => $new_file->parent->path->value.'\\'.$new_file->name
                    ]
                );
                foreach ($destination->services as $service)
                {
                    $new_file->services()->attach($service->id);
                }

                $new_file = $new_file->refresh();

                $to = json_decode( json_encode('public\\'.$new_file->path->value ) );

                $goes_well = Storage::copy(
                    $from,
                    $to
                );

                $new_file->refresh();
            }
        }
        catch (\Throwable $th)
        {

            DB::rollBack();

            return ResponseTrait::get_error($th);
        }


        DB::commit(); // YES --> finalize it
        try
        {
            if ((int)$request->on_exist != 1 )NodeUpdateEvent::dispatch('f', [$new_file->id.'-f'], "add");
        }
        catch (\Throwable $e)
        {}
        return ResponseTrait::get('success', $new_file);

    }
}
