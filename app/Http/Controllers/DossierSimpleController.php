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

//            return $new_folder->parent;

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
                    $errorResponse = ["msg" => "storingError", "value" => "Error : Creating folder not work, return false. Path: "."public\\".$path_value];
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
            $errorResponse = ["msg" => "catchException", "value" => $th->getMessage()];
        }

        if($saved)
        {
            DB::commit(); // YES --> finalize it
            try
            {
                NodeUpdateEvent::dispatch('ds', [$new_folder->id.'-ds'], "add");
            }
            catch (\Throwable $e)
            {}

            return ResponseTrait::get('success', $new_folder);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            $errorResponse = $errorResponse == null ? "Something went wrong !" : $errorResponse;

            return ResponseTrait::get('error', $errorResponse) ;
        }

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

            if(Auth::user()->validator_id == null)
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
                }
                catch (\Throwable $th) {
                    return \response(ResponseTrait::get('error', 'en attente'), 500);

                }

                $new_operation->operable;
                $new_operation->front_type = 'ds';
                Notification::sendNow(User::find(Auth::user()->validator_id), new RemovalNotification('Dossier', $new_operation, Auth::user()));
                DB::commit();
                return ResponseTrait::get('success', 'attente');
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

            return \response(ResponseTrait::get('error', $th2->getMessage()), 500);
        }

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

    public function move_folder(Request $request)
    {

        DB::beginTransaction();

        $goes_well = true;
        $GLOBALS['to_broadcast'] = [];
        $GLOBALS['to_delete'] = [];

        function update_path($folder)
        {
            $parent = $folder->parent;

            $folder->path->value = $parent->path->value."\\".$folder->name;

            $folder->push();
            $folder->refresh();

            foreach ($folder->dossiers as $sub_folder)
            {
                update_path($sub_folder);

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

        function attach_children($old_folder, $new_folder)
        {
            $file_controller = new FichierController();

            foreach ($old_folder->dossiers as $dossier)
            {
                if ( Paths::where([ 'value' => $new_folder->path->value.'\\'.$dossier->name ])->exists() )
                {
                    $existant_folder = Paths::where([ 'value' => $new_folder->path->value.'\\'.$dossier->name ])->first()->routable;

                    attach_children($dossier, $existant_folder);

                    $existant_folder->refresh();
                    $dossier->refresh();
                    array_push($GLOBALS['to_delete'], $dossier);
                }
                else
                {
                    $dossier->parent_id = $new_folder->id;
                    $dossier->parent_type = 'App\Models\DossierSimple';

                    $dossier->push();

                    update_path($dossier);

                    $dossier->refresh();
                    array_push($GLOBALS['to_broadcast'], $dossier);
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

                $fichier->push();

                $file_controller->update_path($fichier);

                $fichier->refresh();
                array_push($GLOBALS['to_broadcast'], $fichier);
            }
        }

        try
        {
            $request->validate([
                'destination_id' => ['required', 'integer'],
                'destination_type' => ['required', 'string', 'max:255'],
                'id' => ['required', 'integer'],
            ]);

            $old_folder = DossierSimple::find($request->id);
            $from = json_decode( json_encode( storage_path().'\app\public\\'.$old_folder->path->value ) );

            $destination = $this->find_node($request->destination_id, $request->destination_type);

//            DossierSimple::where('id', $request->id)->update(
//                [
//                    'parent_id' => $request->destination_id,
//                    'parent_type' => $request->destination_type
//                ]
//            );

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
                        $old_folder->name = $new_name;

                        $old_folder->push();

                        $new_folder = $old_folder->refresh();

                        $to = json_decode( json_encode( storage_path().'\app\public\\'.update_path($new_folder) ) );

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
                        $new_folder = DossierSimple::where(
                            [
                                'name' => $old_folder->name,
                                'parent_id' => (int)$request->destination_id,
                                'parent_type' => $request->destination_type,
                            ]
                        )->first();

//                        foreach ($new_folder->dossiers as $dossier)
//                        {
//                            $dossier->delete();
//                        }
//                        foreach ($new_folder->fichiers as $fichier)
//                        {
//                            $fichier->delete();
//                        }
//                        Storage::deleteDirectory('public\\'.$destination->path->value.'\\'.$old_folder->name);

                        attach_children($old_folder, $new_folder);

                        $new_folder->refresh();
//                        $new_folder->dossiers;
//                        $new_folder->fichiers;

                        $to = json_decode( json_encode( storage_path().'\app\public\\'.$new_folder->path->value ) );

                        File::copyDirectory(
                            $from,
                            $to,
                        );
                        File::deleteDirectory($from);
//                        $res = [$from, $to];
                        $old_folder->refresh();
//                        $this->del_folder(
//                            new Request(
//                                [
//                                    'id' => $old_folder->id,
//                                ]
//                            )
//                        );
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

                $old_folder->push();

                $old_folder->refresh();
                $new_folder = $old_folder;

                $to = json_decode( json_encode( storage_path().'\app\public\\'.update_path($new_folder) ) );

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
            return ResponseTrait::get('error', 'Line: '.$th->getLine().'; '.$th->getMessage());
        }



        if($goes_well)
        {
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
                $getId = function($element)
                {
                    if ($element instanceof DossierSimple) return $element->id.'-ds';
                    elseif ($element instanceof Fichier) return $element->id.'-f';
                };

                if (count($GLOBALS['to_broadcast']) > 0) NodeUpdateEvent::dispatch('ds', array_map( $getId, $GLOBALS['to_broadcast'] ), 'update');
            }
            catch (\Throwable $e)
            {}

            $GLOBALS['to_broadcast'] = [];
            return ResponseTrait::get('success', $new_folder);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            $GLOBALS['to_broadcast'] = [];
            return ResponseTrait::get('error', [$goes_well, $from, $to]);
        }

    }

    public function copy_folder(Request $request)
    {

        DB::beginTransaction();

        $goes_well = true;

        $GLOBALS['to_broadcast'] = [];

        function create_children($old_folder, $new_folder)
        {
            foreach ( $old_folder->dossiers as $dossier )
            {

                if ( Paths::where( [ 'value' => $new_folder->path->value.'\\'.$dossier->name ] )->exists() )
                {
                    $nv_dossier = Paths::where( [ 'value' => $new_folder->path->value.'\\'.$dossier->name ] )->first()->routable;

                    $nv_dossier->services()->detach();
                    foreach ($new_folder->services as $service)
                    {
                        $nv_dossier->services()->attach($service->id);
                    }
                }
                else
                {
                    $nv_dossier = new DossierSimple(
                        [
                            'name' => $dossier->name,
                            'section_id' => $new_folder->section_id,
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

                create_children($dossier, $nv_dossier);
            }
            foreach ( $old_folder->fichiers as $fichier )
            {
                if ( !Paths::where( [ 'value' => $new_folder->path->value.'\\'.$fichier->name ] )->exists() )
                {
                    $new_file = new Fichier(
                        [
                            'name' => $fichier->name,
                            'section_id' => $new_folder->section_id,
                            'size' => $fichier->size,
                            'extension' => $fichier->extension,
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
                else
                {
                    $new_file = Paths::where( [ 'value' => $new_folder->path->value.'\\'.$fichier->name ] )->first()->routable;

                    $new_file->services()->detach();
                    foreach ($new_folder->services as $service)
                    {
                        $new_file->services()->attach($service->id);
                    }
                }
            }
        }

        try
        {
            $request->validate([
                'destination_id' => ['required', 'integer'],
                'destination_type' => ['required', 'string', 'max:255'],
                'id' => ['required', 'integer'],
                'section_id' => ['required', 'integer'],
                'services' => ['required', 'string'],
            ]);

            $old_folder = DossierSimple::find($request->id);
            $from = json_decode( json_encode( storage_path().'\app\public\\'.$old_folder->path->value ) );

            $destination = $this->find_node($request->destination_id, $request->destination_type);

            if (Paths::where([ 'value' => $destination->path->value.'\\'.$old_folder->name ])->exists())
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
                        )->get()[0];
                        break;
                    }
                    case 2:
                    {
                        $num_copy = 1;
                        $new_name = $old_folder->name;

                        while (Storage::exists('public\\'.$destination->path->value.'\\'.$new_name)) {
                            # code...

                            $set_num = $num_copy == 1 ? "" : " ($num_copy)";

                            $new_name = $old_folder->name." - Copie$set_num";

                            $num_copy++;
                        }

                        $renamed_dossier = new DossierSimple(
                            [
                                'name' => $new_name,
                                'section_id' => $destination->section_id ?? $destination->id,
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

                        create_children($old_folder, $renamed_dossier);

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
                        $new_folder = DossierSimple::where(
                            [
                                'name' => $old_folder->name,
                                'parent_id' => (int)$request->destination_id,
                                'parent_type' => $request->destination_type,
                            ]
                        )->first();

//                        foreach ($new_folder->dossiers as $dossier)
//                        {
//                            $dossier->delete();
//                        }
//                        foreach ($new_folder->fichiers as $fichier)
//                        {
//                            $fichier->delete();
//                        }
//                        Storage::deleteDirectory('public\\'.$destination->path->value.'\\'.$old_folder->name);

                        create_children($old_folder, $new_folder);

                        $new_folder->refresh();
//                        $new_folder->dossiers;
//                        $new_folder->fichiers;

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

                create_children($old_folder, $new_folder);

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
            $GLOBALS['to_broadcast'] = [];
            return ResponseTrait::get('error', 'Line: '.$th->getLine().': '.$th->getMessage());
        }



        if($goes_well)
        {
            DB::commit(); // YES --> finalize it
            try
            {
                $getId = function($element)
                {
                    if ($element instanceof DossierSimple) return $element->id.'-ds';
                    elseif ($element instanceof Fichier) return $element->id.'-f';
                };

                if (count($GLOBALS['to_broadcast']) > 0) NodeUpdateEvent::dispatch('ds', array_map( $getId, $GLOBALS['to_broadcast'] ), 'add');
            }
            catch (\Throwable $e)
            {}

            $GLOBALS['to_broadcast'] = [];
            return ResponseTrait::get('success', $new_folder);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            $GLOBALS['to_broadcast'] = [];
            return ResponseTrait::get('error', $goes_well);
        }

    }

    public function test()
    {
        return 'test Réussi';
    }

}
