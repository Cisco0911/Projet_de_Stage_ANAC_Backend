<?php

namespace App\Http\Controllers;

use App\Http\Traits\NodeTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\Fichier;
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
use Illuminate\Support\Facades\Auth;
use App\Models\operationNotification;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Notifications\RemovalNotification;
use Illuminate\Support\Facades\Notification;

class DossierSimpleController extends Controller
{
    //
    use ServiableTrait;
    use ResponseTrait;
    use NodeTrait;

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

//            $var = $this->demand_exist('deletion', $dossier);
//
//            $dossier->is_suppressing = isset($var);
            $ds[$key] = $this->format($dossier);

        }

        return $ds;
    }

    public static function find(int $id)
    {
        $folder = DossierSimple::find($id);
        $folder->section;
        $folder->services;
        $folder->path;
        $folder->parent;
        $folder->dossiers;
        $folder->fichiers;
        $folder->operation;

        return $folder;
    }

    public function add_folder(Request $request)
    {

        DB::beginTransaction();

        try {
            //code...

            $request->validate([
                'section_id' => ['required', 'integer'],
                'name' => ['required', 'string', 'max:255'],
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

            if( $feasible != 2 ) throw new Exception("Vous n'avez pas les droits n??cessaires\nSi le parent est valid??, veuillez faire une demande d'autorisation de modification", -3);

            $new_folder = $parent->dossiers()->create(
                [
                    'section_id' => $request->section_id,
                    'name' => $request->name,
                    'is_validated' => $parent->is_validated ?? 0,
                    'validator_id' => $parent->validator_id,
                ]
            );

//            return $new_folder->parent;

            $path_value = $new_folder->parent->path->value."\\".$new_folder->name;

            if (!Paths::where([ 'value' => $path_value ])->exists()) {
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
                }
                else
                {
                    throw new Exception('Erreur de stockage: La cr??ation en stockage a ??chou??.', 1);
                }

            }
            else
            {
                throw new Exception('Le dossier existe d??j??.', 0);
            }


        }
        catch (\Throwable $th)
        {
            DB::rollBack();
            return ResponseTrait::get_error($th) ;
        }


        DB::commit(); // YES --> finalize it
        try
        {
            NodeUpdateEvent::dispatch('ds', [$new_folder->id.'-ds'], "add");
        }
        catch (\Throwable $e)
        {}

        return ResponseTrait::get_success($new_folder);

        // DB::endTransaction();

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

                    return ResponseTrait::get_info("Demande de permission envoy??");
                }

            }
            else
            {
                throw new Exception("Vous n'avez pas les droits n??cessaires");
            }
            // RemovalEvent::dispatch('Dossier', $cache, Auth::user());

        }
        catch (\Throwable $th2)
        {
            //throw $th;
            $goesWell = false;
        }

        if($goesWell)
        {
            Storage::deleteDirectory($pathInStorage);
            DB::commit(); // YES --> finalize it

            $info = json_decode('{}');
            $info->id = $cache->id; $info->type = 'ds';

            NodeUpdateEvent::dispatch('ds', $info, "delete");

            return ResponseTrait::get('success', $target);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get('error', $th2->getMessage());
        }

    }

    function update_folder( Request $request )
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

            $folder = DossierSimple::find($request->id);

            switch ($request->update_object)
            {
                case 'is_validated':
                {

                    if ( !$this->can_modify_valid_state($folder) )
                    {
                        if ($folder->is_validated)
                        {
                            if ($this->can_modify_node($folder))
                            {
                                if ( $this->ask_permission_for('modification', $folder) )
                                {
                                    $GLOBALS['to_broadcast'] = [];

                                    DB::commit();

                                    return ResponseTrait::get_info("Demande de permission envoy??");
                                }
                                else
                                {
                                    $GLOBALS['to_broadcast'] = [];

                                    DB::rollBack();

                                    throw new Exception("Demande existant");
                                }

                            }
                            else throw new Exception("Vous n'avez pas les droits n??cessaires", -2);
                        }
                        else throw new Exception("Vous n'avez pas les droits n??cessaires", -2);
                    }

                    if ($request->new_value)
                    {
                        $folder = $this->valid_node($folder);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }
                    else
                    {
                        $folder = $this->unvalid_node($folder);

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

            NodeUpdateEvent::dispatch('ds', array_map( $getId, $are_updated ), "update");
        }
        else NodeUpdateEvent::dispatch('ds', [$this->get_broadcast_id($folder)], "update");

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get('success', $folder);

    }

    public function update_services($parent, $child)
    {
        $child->services()->detach();
        foreach ($parent->services as $service)
        {
            $child->services()->attach($service->id);
        }

        if ( !($child instanceof Fichier) )
        {
            foreach ($child->dossiers as $dossier)
            {
                $this->update_services($child, $dossier);
            }

            foreach ($child->fichiers as $fichier)
            {
                $this->update_services($child, $fichier);
            }
        }
    }

    protected function update_path($folder)
    {
        $parent = $folder->parent;

        $folder->path->value = $parent->path->value."\\".$folder->name;

        $folder->push();
        $folder->refresh();

        foreach ($folder->dossiers as $sub_folder)
        {
            $this->update_path($sub_folder);

            $sub_folder->refresh();
            array_push($GLOBALS['to_broadcast'], $sub_folder);
        }

        foreach ( $folder->fichiers as $fichier )
        {
            $file_controller = new FichierController();

            $file_controller->update_path($fichier);

            $fichier->refresh();
            array_push($GLOBALS['to_broadcast'], $fichier);
        }

        return $folder->path->value;

    }

    protected function attach_children($old_folder, $new_folder)
    {
        $file_controller = new FichierController();

        foreach ($old_folder->dossiers as $dossier)
        {
            if ( Paths::where([ 'value' => $new_folder->path->value.'\\'.$dossier->name ])->exists() )
            {
                $existant_folder = Paths::where([ 'value' => $new_folder->path->value.'\\'.$dossier->name ])->first()->routable;

                $this->attach_children($dossier, $existant_folder);

                $existant_folder->refresh();
                $dossier->refresh();
//                    array_push($GLOBALS['to_delete'], $dossier);
            }
            else
            {
                $dossier->parent_id = $new_folder->id;
                $dossier->parent_type = 'App\Models\DossierSimple';
                $dossier->is_validated = $new_folder->is_validated;
                $dossier->validator_id = $new_folder->validator_id;

                $dossier->push();

                $this->update_path($dossier);

                $dossier->refresh();
//                    array_push($GLOBALS['to_broadcast'], $dossier);
            }
        }
        foreach ($old_folder->fichiers as $fichier)
        {

            if ( Paths::where([ 'value' => $new_folder->path->value.'\\'.$fichier->name ])->exists() )
            {
                $path = Paths::where([ 'value' => $new_folder->path->value.'\\'.$fichier->name ])->first();

                $existant_file = $path->routable;

                $file_controller->del_file(
                    new Request(
                        [
                            'id' => $existant_file->id,
                        ]
                    )
                );

            }

            $fichier->parent_id = $new_folder->id;
            $fichier->parent_type = 'App\Models\DossierSimple';
            $fichier->is_validated = $new_folder->is_validated;
            $fichier->validator_id = $new_folder->validator_id;

            $fichier->push();

            $file_controller->update_path($fichier);

            $fichier->refresh();
//                array_push($GLOBALS['to_broadcast'], $fichier);
        }
    }

    public function move_folder(Request $request)
    {

        DB::beginTransaction();

        $goes_well = true;
        $GLOBALS['to_broadcast'] = [];
        $GLOBALS['to_delete'] = [];

        try
        {
            $request->validate([
                'destination_id' => ['required', 'integer'],
                'destination_type' => ['required', 'string', 'max:255'],
                'id' => ['required', 'integer'],
            ]);

            $new_parent = $this->find_node($request->destination_id, $request->destination_type);

            $old_folder = DossierSimple::find($request->id);

            if (empty($new_parent))
            {
                throw new Exception("Parent inexistant.", -4);
            }

            $feasible = $this->can_modify_node($new_parent);

            if( ($feasible != 2) || !$this->can_modify_node_deep_check($old_folder) ) throw new Exception("Vous n'avez pas les droits n??cessaires ou le dossier contient un ??l??ment valid??\nSi le parent ou le dossier est valid??, veuillez faire une demande d'autorisation de modification", -3);

            $from = json_decode( json_encode( storage_path().'\app\public\\'.$old_folder->path->value ) );

            $destination = $new_parent;

            if (strpos($destination->path->value, $old_folder->path->value) !== false) throw new Exception('Le dossier de destination est un sous-dossier du dossier source.', -1);

            if ( Paths::where([ 'value' => $destination->path->value.'\\'.$old_folder->name ])->exists() )
            {
                switch ((int)$request->on_exist)
                {
                    case 1:
                    {
                        $new_folder = DossierSimple::where(
                            [
                                'name' => $old_folder->name,
                                'parent_id' => (int)$request->destination_id,
                                'parent_type' => $request->destination_type,
                            ]
                        )->first();
                        break;
                    }
                    case 2:
                    {
                        $num_copy = 1;
                        $new_name = $old_folder->name;

                        while (Paths::where([ 'value' => $destination->path->value.'\\'.$new_name ])->exists()) {
                            # code...

                            $set_num = $num_copy == 1 ? "" : " ($num_copy)";

                            $new_name = $old_folder->name." - Copie$set_num";

                            $num_copy++;
                        }

                        $old_folder->parent_id = $request->destination_id;
                        $old_folder->parent_type = $request->destination_type;
                        $old_folder->is_validated = $destination->is_validated ?? 0;
                        $old_folder->validator_id = $destination->validator_id;
                        $old_folder->name = $new_name;

                        $old_folder->push();

                        $new_folder = $old_folder->refresh();

                        $to = json_decode( json_encode( storage_path().'\app\public\\'. $this->update_path($new_folder)) );

                        $goes_well = File::moveDirectory(
                            $from,
                            $to,
                            true
                        );

                        $new_folder->refresh();
                        array_push($GLOBALS['to_broadcast'], $new_folder);

                        break;
                    }
                    case 3:
                    {
                        $new_folder = $destination->dossiers()->where(
                            [
                                'name' => $old_folder->name,
                            ]
                        )->first();

                        if (!$this->can_modify_node_deep_check($new_folder)) throw new Exception("Le dossier ?? la destination est soit valid?? ou contient un ??l??ment valid??");

                        if ( $this->can_modify_node($new_folder) == 2 )
                        {
                            $this->attach_children($old_folder, $new_folder);

                            $new_folder->refresh();

                            $to = json_decode( json_encode( storage_path().'\app\public\\'.$new_folder->path->value ) );

                            File::copyDirectory(
                                $from,
                                $to,
                            );
                        }

                        File::deleteDirectory($from);

                        $old_folder->refresh();

                        array_push($GLOBALS['to_delete'], $old_folder);

                        break;
                    }
                }
//                return $res;
            }
            else
            {
                $old_folder->parent_id = $request->destination_id;
                $old_folder->parent_type = $request->destination_type;
                $old_folder->is_validated = $destination->is_validated ?? 0;
                $old_folder->validator_id = $destination->validator_id;

                $old_folder->push();
                $old_folder->refresh();

                $new_folder = $old_folder;

                $to = json_decode( json_encode( storage_path().'\app\public\\'. $this->update_path($new_folder)) );

                $goes_well = File::moveDirectory(
                    $from,
                    $to,
                    true
                );

                $new_folder->refresh();
                array_push($GLOBALS['to_broadcast'], $new_folder);
            }

            $this->update_services($destination, $new_folder);
        }
        catch (\Throwable $th)
        {
            $GLOBALS['to_broadcast'] = [];
            $GLOBALS['to_delete'] = [];

            DB::rollBack();

            return ResponseTrait::get_error($th);
        }


        DB::commit(); // YES --> finalize it
        foreach ($GLOBALS['to_delete'] as $element)
        {
            $this->del_folder(
                new Request(
                    [
                        'id' => $element->id,
                    ]
                )
            );
        };
        $GLOBALS['to_delete'] = [];
        try
        {
            $getId = function($element) { return $this->get_broadcast_id($element); };

            if (count($GLOBALS['to_broadcast']) > 0) NodeUpdateEvent::dispatch('ds', array_map( $getId, $GLOBALS['to_broadcast'] ), 'update');
        }
        catch (\Throwable $e)
        {}

        $GLOBALS['to_broadcast'] = [];
        return ResponseTrait::get_success($new_folder);

    }

    protected function create_children($old_folder, $new_folder)
    {
        $file_controller = new FichierController();

        foreach ( $old_folder->dossiers as $dossier )
        {

            if ( Paths::where( [ 'value' => $new_folder->path->value.'\\'.$dossier->name ] )->exists() )
            {
                $nv_dossier = Paths::where( [ 'value' => $new_folder->path->value.'\\'.$dossier->name ] )->first()->routable;

//                $nv_dossier->is_validated = $new_folder->is_validated;
//                $nv_dossier->validator_id = $new_folder->validator_id;

                $nv_dossier->services()->detach();
                foreach ($new_folder->services as $service)
                {
                    $nv_dossier->services()->attach($service->id);
                }

                $nv_dossier->push();
                $nv_dossier->refresh();
            }
            else
            {
                $nv_dossier = new DossierSimple(
                    [
                        'name' => $dossier->name,
                        'section_id' => $new_folder->section_id,
                        'is_validated' => $new_folder->is_validated,
                        'validator_id' => $new_folder->validator_id,
                    ]
                );

                $new_folder->dossiers()->save($nv_dossier);
                $new_folder->refresh();

                $nv_dossier->path()->create(
                    [
                        'value' => $new_folder->path->value.'\\'.$nv_dossier->name
                    ]
                );
                foreach ($new_folder->services as $service)
                {
                    $nv_dossier->services()->attach($service->id);
                }

                array_push($GLOBALS['to_broadcast'], $nv_dossier);
            }

            $this->create_children($dossier, $nv_dossier);
        }
        foreach ( $old_folder->fichiers as $fichier )
        {

            if ( Paths::where( [ 'value' => $new_folder->path->value.'\\'.$fichier->name ] )->exists() )
            {
                $existing_file = Paths::where( [ 'value' => $new_folder->path->value.'\\'.$fichier->name ] )->first()->routable;

                $file_controller->del_file(
                    new Request(
                        [
                            'id' => $existing_file->id,
                        ]
                    )
                );
            }

            $new_file = new Fichier(
                [
                    'name' => $fichier->name,
                    'section_id' => $new_folder->section_id,
                    'size' => $fichier->size,
                    'extension' => $fichier->extension,
                    'is_validated' => $new_folder->is_validated,
                    'validator_id' => $new_folder->validator_id,
                ]
            );

            $new_folder->fichiers()->save($new_file);
            $new_folder->refresh();

            $new_file->path()->create(
                [
                    'value' => $new_folder->path->value.'\\'.$new_file->name
                ]
            );
            foreach ($new_folder->services as $service)
            {
                $new_file->services()->attach($service->id);
            }

//                $new_file->path;
            array_push($GLOBALS['to_broadcast'], $new_file);

        }
    }

    public function copy_folder(Request $request)
    {

        DB::beginTransaction();

        $goes_well = true;

        $GLOBALS['to_broadcast'] = [];
//        if ((int)$request->on_exist === -1)
//        {
//            DB::rollBack();
//            return $request;
//        }

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

            if( $feasible != 2 ) throw new Exception("Vous n'avez pas les droits n??cessaires\nSi le parent est valid??, veuillez faire une demande d'autorisation de modification", -3);

//            throw new Exception('lalala');

            $old_folder = DossierSimple::find($request->id);
            $from = json_decode( json_encode( storage_path().'\app\public\\'.$old_folder->path->value ) );

            $destination = $new_parent;

            if (strpos($destination->path->value, $old_folder->path->value) !== false) throw new Exception('Le dossier de destination est un sous-dossier du dossier source.', -1);

            if (Paths::where([ 'value' => $destination->path->value.'\\'.$old_folder->name ])->exists())
            {
                switch ((int)$request->on_exist)
                {
                    case 1:
                    {
                        $new_folder = $destination->dossiers()->where(
                            [
                                'name' => $old_folder->name,
                            ]
                        )->first();
                        break;
                    }
                    case 2:
                    {
//                        return $request;
                        $num_copy = 1;
                        $new_name = $old_folder->name;

                        while ( Paths::where([ 'value' => $destination->path->value.'\\'.$new_name ])->exists() )
                        {
                            # code...

                            $set_num = $num_copy == 1 ? "" : " ($num_copy)";

                            $new_name = $old_folder->name." - Copie$set_num";

                            $num_copy++;
                        }

                        $renamed_dossier = new DossierSimple(
                            [
                                'name' => $new_name,
                                'section_id' => $destination->section_id ?? $destination->id,
                                'is_validated' => $destination->is_validated ?? 0,
                                'validator_id' => $destination->validator_id,
                            ]
                        );

                        $destination->dossiers()->save($renamed_dossier);
                        $destination->refresh();

                        array_push($GLOBALS['to_broadcast'], $renamed_dossier);

                        $renamed_dossier->path()->create(
                            [
                                'value' => $renamed_dossier->parent->path->value.'\\'.$renamed_dossier->name
                            ]
                        );
                        foreach ($destination->services as $service)
                        {
                            $renamed_dossier->services()->attach($service->id);
                        }

                        $renamed_dossier->refresh();

                        $this->create_children($old_folder, $renamed_dossier);

                        $renamed_dossier->refresh();
//                        $renamed_dossier->dossiers; $renamed_dossier->fichiers;
//                        $renamed_dossier->dossiers[0]->fichiers;

                        $to = json_decode( json_encode( storage_path().'\app\public\\'.$renamed_dossier->path->value ) );

                        File::copyDirectory(
                            $from,
                            $to,
                        );

                        $new_folder = $renamed_dossier;

                        break;
                    }
                    case 3:
                    {
                        $new_folder = $destination->dossiers()->where(
                            [
                                'name' => $old_folder->name,
                            ]
                        )->first();

                        if (!$this->can_modify_node_deep_check($new_folder)) throw new Exception("Le dossier ?? la destination est soit valid?? ou contient un ??l??ment valid??");


                        $this->create_children($old_folder, $new_folder);

                        $new_folder->refresh();

                        $to = json_decode( json_encode( storage_path().'\app\public\\'.$new_folder->path->value ) );

                        File::copyDirectory(
                            $from,
                            $to,
                        );

                        break;
                    }
                }
            }
            else
            {

                $new_folder = new DossierSimple(
                    [
                        'name' => $old_folder->name,
                        'section_id' => $destination->section_id ?? $destination->id,
                        'is_validated' => $destination->is_validated ?? 0,
                        'validator_id' => $destination->validator_id,
                    ]
                );

                $destination->dossiers()->save($new_folder);
                $destination->refresh();

                array_push($GLOBALS['to_broadcast'], $new_folder);

                $new_folder->path()->create(
                    [
                        'value' => $new_folder->parent->path->value.'\\'.$new_folder->name
                    ]
                );
                foreach ($destination->services as $service)
                {
                    $new_folder->services()->attach($service->id);
                }

                $new_folder->refresh();

                $this->create_children($old_folder, $new_folder);

                $new_folder->refresh();
//                        $new_folder->dossiers; $new_folder->fichiers;
//                        $new_folder->dossiers[0]->fichiers;

                $to = json_decode( json_encode( storage_path().'\app\public\\'.$new_folder->path->value ) );

                File::copyDirectory(
                    $from,
                    $to,
                );

            }

//            return $new_folder;

        }
        catch (\Throwable $th)
        {
            return ResponseTrait::get_error($th);
        }



        DB::commit(); // YES --> finalize it
        try
        {
            $getId = function($element) { return $this->get_broadcast_id($element); };

            if (count($GLOBALS['to_broadcast']) > 0) NodeUpdateEvent::dispatch('ds', array_map( $getId, $GLOBALS['to_broadcast'] ), 'add');
        }
        catch (\Throwable $e)
        {}

        $GLOBALS['to_broadcast'] = [];
        return ResponseTrait::get_success($new_folder);

    }

    public function test()
    {
        return 'test R??ussi';
    }

}
