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

/**
 *
 */
class FichierController extends Controller
{
    //
    use ServiableTrait;
    use ResponseTrait;
    use NodeTrait;

    /**
     * @param int $id
     * @return Fichier
     */
    public static function find(int $id) : Fichier | null
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

            if ($file->is_validated) $file->validator = UserController::find($file->validator_id);
        }

        return $file;
    }

    /**
     * @param $element
     * @return mixed
     */
    public static function format($element)
    {
        $element->services;

        if ($element->is_validated) $element->validator = UserController::find($element->validator_id);

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


    /**
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function get_fs()
    {
       $fs = Fichier::all();

       foreach ($fs as $key => $fichier) $fs[$key] = self::format($fichier);

       return $fs;
    }

    /**
     * @param $id
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function overview_of($id)
    {
        $path = Fichier::find($id)->path->value;

        return response()->file(\storage_path("app/public/$path"));
    }

    /**
     * @param Request $request
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function download_file(Request $request)
    {
        $file = self::find($request->id);

        return response()->download(\storage_path("app/public/{$file->path->value}"), $file->name);
    }

    /**
     * @param $destination
     * @param $old_name
     * @return string
     */
    protected function getNewName($destination, $old_name) : string
    {
        $num_copy = 1;
        $new_name = $old_name;

        $pathInfo = pathinfo($old_name);

        while (Paths::where([ 'value' => $destination->path->value.'/'.$new_name ])->exists()) {
            # code...

            $set_num = $num_copy == 1 ? "" : " ($num_copy)";

            $new_name = $pathInfo["filename"]." - Copie$set_num.{$pathInfo["extension"]}";

            $num_copy++;
        }

        return $new_name;
    }

    /**
     * @param Request $request
     * @return array
     */
    public function add_files(Request $request)
    {

        DB::beginTransaction();

        $saved = true;
        $added_files = [];
        $duplicated_files = [];
        $new_files = [];

        try {

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

                $dir = $parent->path->value;

                $full_name = $this->getNewName($parent, $full_name);

                $path_value = $parent->path->value."/$full_name";

                if ( Storage::exists("public/".$path_value) ) Storage::delete("public/".$path_value);

                if (!Storage::exists("public/".$path_value)) {
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


                    if (Storage::putFileAs("public/".$dir, $file, $full_name)) {

                        $services = json_decode($request->services);

                        $this->add_to_services($services, $new_file->id, 'App\Models\Fichier');

                        array_push($added_files, "public/".$path->value);
                        array_push($new_files, $new_file);
                    }
                    else
                    {
                        throw new Exception('Erreur de stockage: Le stockage du fichier a échoué.', 1);
                    }

                }
                else throw new Exception("L'ajout du(des) fichier(s) a échoué", -100);

                if(!is_null($double)) array_push($duplicated_files, $double);

                ActivitiesHistoryController::record_activity($new_file, "add");
            }


        }
        catch (\Throwable $th)
        {
            Storage::delete($added_files);
            DB::rollBack();
            return ResponseTrait::get_error($th);
        }
        DB::commit(); // YES --> finalize it
        // $new_file->url = "http://localhost/overview_of?id=".$new_file->id;
        // $new_file->parent_type = "llllo";

        $getId = function($element){ return $element->id.'-f'; };

        NodeUpdateEvent::dispatch($new_file->services()->get(), array_map( $getId, $new_files ), 'add');

        $files = new \stdClass();
        $idx = 0;

        foreach ($new_files as $new_file)
        {
            $files->{$idx} = $new_file;
            $idx++;
        }

        return ResponseTrait::get_success($files);

    }

    /**
     * @param Request $request
     * @return array
     */
    public function del_file(Request $request)
    {

        DB::beginTransaction();

        try {

            $file = Fichier::find($request->id);

            if (!$file) throw new Exception("Fichier inexistant !!");

            $cache = $this->format($file);

            $services = $file->services()->get();

            $feasible = $this->can_modify_node($file);

            $services_names = [];

            foreach ($services as $service) array_push($services_names, $service->name);

            if($feasible)
            {
                if ($feasible == 2)
                {
                    // dd($request);

                    $pathInStorage = "public/".$file->path->value;

                    $file->delete();
                }
                else
                {
                    $this->ask_permission_for('deletion', $file);

                    DB::commit();

                    return ResponseTrait::get_info("Demande de permission envoyé");
                }
            }
            else
            {
                throw new Exception("Vous n'avez pas les droits nécessaires");
            }

            ActivitiesHistoryController::record_activity($file, "delete", $services_names);

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        Storage::delete($pathInStorage);
        DB::commit(); // YES --> finalize it

        $info = json_decode('{}');
        $info->id = $cache->id; $info->type = 'f';

        NodeUpdateEvent::dispatch($services, $info, 'delete');

        return ResponseTrait::get_success($file);

    }

    /**
     * @param Request $request
     * @return array
     */
    function update_file(Request $request )
    {

        DB::beginTransaction();

        $GLOBALS['to_broadcast'] = [];

        try
        {

            $request->validate([
                'id' => ['required', 'integer'],
                'update_object' => ['required', 'string'],
                'new_value' => ['required'],
            ]);

            $file = Fichier::find($request->id);

            if (!$file) throw new Exception("Fichier inexistant !!");

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

                    ActivitiesHistoryController::record_activity($file, $file->is_validated ? "validate" : "invalidate");

                    break;
                }
                case 'name':
                {

                    if($this->can_modify_node($file) !== 2) throw new Exception("Vous n'avez pas les droits nécessaires", -2);

                    $from = $file->path->value;

                    $path_info = pathinfo($from);

                    if ( Paths::where([ 'value' => "{$file->parent->path->value}/{$request->new_value}.{$path_info["extension"]}" ])->exists() ) throw new Exception("Un fichier du même emplacement porte déjà ce nom !", -1);

                    $file->name = "{$request->new_value}.{$path_info["extension"]}";

                    $file->push();
                    $file->refresh();

                    $to = $this->update_path($file);

                    if (empty($to)) throw new Exception("Une erreur est survenue !");

                    if ( Storage::exists("public/$to") )
                    {
                        Storage::deleteDirectory("public/$to");
                        Storage::delete("public/$to");
                    }

                    rename( storage_path("app/public/$from"), storage_path("app/public/$to") );

                    $file->push();
                    $file->refresh();

                    ActivitiesHistoryController::record_activity($file, "rename");

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

            NodeUpdateEvent::dispatch($file->services()->get(), array_map( $getId, $are_updated ), "update");
        }
        else NodeUpdateEvent::dispatch($file->services()->get(), [$this->get_broadcast_id($file)], "update");

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get_success($file);

    }

    /**
     * @param $file
     * @return string
     */
    public function update_path($file)
    {
        $parent = $file->parent;

        $file->path->value = $parent->path->value."/".$file->name;

        $file->push();
        $file->refresh();

        return $file->path->value;

    }

    /**
     * @param Request $request
     * @return array
     */
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

            $from = json_decode( json_encode( 'public/'.$old_file->path->value ) );

            $destination = $new_parent;

//            DossierSimple::where('id', $request->id)->update(
//                [
//                    'parent_id' => $request->destination_id,
//                    'parent_type' => $request->destination_type
//                ]
//            );

            if ( Paths::where([ 'value' => $destination->path->value.'/'.$old_file->name ])->exists() )
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
                        $new_name = $this->getNewName($destination, $old_file->name);

                        $old_file->name = $new_name;

                        $old_file->parent_id = $request->destination_id;
                        $old_file->parent_type = $request->destination_type;
                        $old_file->is_validated = $destination->is_validated ?? 0;
                        $old_file->validator_id = $destination->validator_id;

                        $old_file->push();

                        $new_file = $old_file->refresh();

                        $to = json_decode( json_encode('public/'.$this->update_path($new_file) ) );

                        $goes_well = Storage::move(
                            $from,
                            $to
                        );

                        $new_file->refresh();

                        break;
                    }
                    case 3:
                    {
                        $path = Paths::where([ 'value' => $destination->path->value.'/'.$old_file->name ])->first();

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

                        $to = json_decode( json_encode('public/'.$this->update_path($new_file) ) );

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
                $old_file->is_validated = $destination->is_validated ?? 0;
                $old_file->validator_id = $destination->validator_id;

                $old_file->push();

                $new_file = $old_file->refresh();

                $to = json_decode( json_encode('public/'.$this->update_path($new_file) ) );

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

            ActivitiesHistoryController::record_activity($new_file, "move");
        }
        catch (\Throwable $th)
        {
            DB::rollBack();

            return ResponseTrait::get_error($th);
        }


        DB::commit(); // YES --> finalize it
        try
        {
            NodeUpdateEvent::dispatch($new_parent->services()->get(), [$new_file->id.'-f'], "update");
        }
        catch (\Throwable $e)
        {}
        return ResponseTrait::get('success', $new_file);

    }

    /**
     * @param Request $request
     * @return array
     */
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
            $from = json_decode( json_encode( 'public/'.$old_file->path->value ) );

            $destination = $new_parent;


            if ( Paths::where([ 'value' => $destination->path->value.'/'.$old_file->name ])->exists() )
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
                        $new_name = $this->getNewName($destination, $old_file->name);

                        $new_file = new Fichier(
                            [
                                'name' => $new_name,
                                'size' => $old_file->size,
                                'extension' => $old_file->extension,
                                'is_validated' => $destination->is_validated ?? 0,
                                'validator_id' => $destination->validator_id,
                                'section_id' => $destination->section_id ?? $destination->id,
                            ]
                        );

                        $destination->fichiers()->save($new_file);
                        $destination->refresh();

                        $new_file->path()->create(
                            [
                                'value' => $new_file->parent->path->value.'/'.$new_file->name
                            ]
                        );
                        foreach ($destination->services as $service)
                        {
                            $new_file->services()->attach($service->id);
                        }

                        $new_file = $new_file->refresh();

                        $to = json_decode( json_encode('public/'.$new_file->path->value ) );

                        $goes_well = Storage::copy(
                            $from,
                            $to
                        );

                        $new_file->refresh();

                        break;
                    }
                    case 3:
                    {
                        $path = Paths::where([ 'value' => $destination->path->value.'/'.$old_file->name ])->first();

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
                                'value' => $new_file->parent->path->value.'/'.$new_file->name
                            ]
                        );
                        foreach ($destination->services as $service)
                        {
                            $new_file->services()->attach($service->id);
                        }

                        $new_file = $new_file->refresh();

                        $to = json_decode( json_encode('public/'.$new_file->path->value ) );

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
                        'is_validated' => $destination->is_validated ?? 0,
                        'validator_id' => $destination->validator_id,
                        'section_id' => $destination->section_id ?? $destination->id,
                    ]
                );

                $destination->fichiers()->save($new_file);
                $destination->refresh();

                $new_file->path()->create(
                    [
                        'value' => $new_file->parent->path->value.'/'.$new_file->name
                    ]
                );
                foreach ($destination->services as $service)
                {
                    $new_file->services()->attach($service->id);
                }

                $new_file = $new_file->refresh();

                $to = json_decode( json_encode('public/'.$new_file->path->value ) );

                $goes_well = Storage::copy(
                    $from,
                    $to
                );

                $new_file->refresh();
            }

            ActivitiesHistoryController::record_activity($new_file, "copy");
        }
        catch (\Throwable $th)
        {

            DB::rollBack();

            return ResponseTrait::get_error($th);
        }


        DB::commit(); // YES --> finalize it
        try
        {
            if ((int)$request->on_exist != 1 )NodeUpdateEvent::dispatch($new_parent->services()->get(), [$new_file->id.'-f'], "add");
        }
        catch (\Throwable $e)
        {}
        return ResponseTrait::get('success', $new_file);

    }
}
