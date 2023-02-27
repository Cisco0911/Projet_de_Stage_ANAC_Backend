<?php

namespace App\Http\Controllers;

use App\Http\Traits\NodeTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\Nc;
use App\Models\User;
use App\Models\Audit;
use App\Models\Paths;
use App\Models\checkList;
use App\Models\Serviable;
use Exception;
use Illuminate\Http\Request;
use App\Models\DossierPreuve;
use App\Events\NodeUpdateEvent;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\ServiableTrait;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Models\operationNotification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\RemovalNotification;
use Illuminate\Support\Facades\Notification;

class AuditController extends Controller
{
    //
    use ServiableTrait;
    use ResponseTrait;
    use NodeTrait;

    public static function find(int $id) :Audit | null
    {
        $audit = Audit::find($id);
        if ($audit)
        {
            $audit->services;
            $audit->section;
            $audit->operation;
            $audit->user;
            $audit->users;
            $audit->checkList;
            $audit->dossier_preuve;
            $audit->nc;
            $audit->path;
            $audit->dossiers;
            $audit->fichiers;

            if ($audit->is_validated) $audit->validator = UserController::find($audit->validator_id);
        }

        return $audit;
    }

    public static function format($element)
    {
        $element->services;
        $element->user;
        $element->users;

        if ($element->is_validated) $element->validator = UserController::find($element->validator_id);

        $node = json_decode($element);

        return $node;
    }


    public function get_audits()
    {
       $audits = Audit::all();

       foreach ($audits as $key => $audit) $audits[$key] = self::format($audit);

       return $audits;
    }

    public function add_audit(Request $request)
    {
        DB::beginTransaction();

        $audit_family = [];

        try {
            //code...

            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'section_id' => ['required', 'integer'],
                'services' => ['required', 'json'],
                'inspectors' => ['required', 'json'],
                'ra_id' => ['nullable', 'integer'],
            ]);

            $parent = $this->find_node($request->section_id, "App\Models\Section");

            if (empty($parent))
            {
                throw new Exception("Parent inexistant.", -4);
            }

            $feasible = $this->can_modify_node($parent);

            if( $feasible != 2 ) throw new Exception("Vous n'avez pas les droits nécessaires", -3);

            $path_value = $parent->path->value."/".$request->name;
            if (Paths::where([ 'value' => $path_value ])->exists())
            {
                throw new Exception("L'audit existe déjà.", 0);
            }

            try {
//                $new_audit = new Audit(
//                    [
//                        'name' => $request->name,
//                        'section_id' => $request->section_id,
////                        'user_id' => $request->ra_id,
//                    ]
//                );
                $new_audit = Auth::user()->audits()->create(
                    [
                        'name' => $request->name,
                        'section_id' => $request->section_id,
                    ]
                );

                $new_audit->push();
            }
            catch (\Throwable $th)
            {
                throw $th;
            }

            $inspector_ids = json_decode($request->inspectors) ?? [Auth::user()->id];

            foreach ($inspector_ids as $inspector_id)
            {
                $new_audit->users()->attach($inspector_id);
            }
            $new_audit->refresh();

            $audit_path = $new_audit->section->path->value.'/'.$new_audit->name;

//            if (Paths::where([ 'value' => $audit_path ])->exists()) throw new Exception("L'audit existe déjà.", 0);


//            $new_checkList = checkList::create( ['audit_id'=> $new_audit->id, 'section_id' => $request->section_id] );
            $new_checkList = $new_audit->checklist()->create( [ 'section_id' => $request->section_id ] );
            $new_checkList->name = 'checkList';
//            $new_checkList->sub_type = 'checkList';

            $new_dp = $new_audit->dossier_preuve()->create( [ 'section_id' => $request->section_id ] );
            $new_dp->name = 'Dossier Preuve';
//            $new_dp->sub_type = 'dp';

            $new_nonC = $new_audit->nc()->create( [ 'section_id' => $request->section_id ] );
            $new_nonC->name = 'NC';
//            $new_nonC->sub_type = 'nonC';


            $pathAudit = Paths::create(
                [
                    'value' => $audit_path,
                    'routable_id' => $new_audit->id,
                    'routable_type' => 'App\Models\Audit'
                ]
            );

            $pathCheckList = Paths::create(
                [
                    'value' => $pathAudit->value.'/CheckList',
                    'routable_id' => $new_checkList->id,
                    'routable_type' => 'App\Models\checkList'
                ]
            );

            $pathDp = Paths::create(
                [
                    'value' => $pathAudit->value.'/Dossier Preuve',
                    'routable_id' => $new_dp->id,
                    'routable_type' => 'App\Models\DossierPreuve'
                ]
            );

            $pathNc = Paths::create(
                [
                    'value' => $pathAudit->value.'/NC',
                    'routable_id' => $new_nonC->id,
                    'routable_type' => 'App\Models\Nc'
                ]
            );

//            return $pathCheckList->value;

            if (Storage::makeDirectory("public/".$pathCheckList->value) &&
                Storage::makeDirectory("public/".$pathDp->value) &&
                Storage::makeDirectory("public/".$pathNc->value)) {


                $services = json_decode($request->services);

                $this->add_to_services($services, $new_audit->id, 'App\Models\Audit');
                $this->add_to_services($services, $new_checkList->id, 'App\Models\checkList');
                $this->add_to_services($services, $new_dp->id, 'App\Models\DossierPreuve');
                $this->add_to_services($services, $new_nonC->id, 'App\Models\Nc');

                array_push($audit_family, $new_audit, $new_checkList, $new_dp, $new_nonC);
            }
            else
            {
                throw new Exception('Erreur de stockage: La création en stockage a échoué.', 1);
            }

            ActivitiesHistoryController::record_activity($new_audit, "add");

        }
        catch (\Throwable $th2)
        {
            DB::rollBack();
            return ResponseTrait::get_error($th2) ;
        }

        DB::commit(); // YES --> finalize it

        $getId = function($element){ return $this->get_broadcast_id($element); };

        NodeUpdateEvent::dispatch($new_audit->services()->get(), array_map( $getId, $audit_family ), 'add');

        return ResponseTrait::get_success(self::find($new_audit->id));
    }

    function del_audit(Request $request)
    {

        DB::beginTransaction();

        $goesWell = true;


        try {

            $audit = Audit::find($request->id);

            if (!$audit) throw new Exception("Audit inexistant !!");

            $cache = $this->format($audit);

            $feasible = $this->can_modify_node($audit);

            $services_names = [];

            foreach ($audit->services as $service) array_push($services_names, $service->name);

            if($feasible)
            {
                if ($feasible == 2)
                {
                    // dd($request);

                    $pathInStorage = "public/".$audit->path->value;

                    $audit->delete();
                }
                else
                {
                    $this->ask_permission_for('deletion', $audit);

                    DB::commit();

                    return ResponseTrait::get_info("Demande de permission envoyé");
                }
            }
            else
            {
                throw new Exception("Vous n'avez pas les droits nécessaires");
            }

            ActivitiesHistoryController::record_activity($audit, "delete", $services_names);
        }
        catch (\Throwable $th) {
            //throw $th;
            $goesWell = false;
        }

        if($goesWell)
        {
            Storage::deleteDirectory($pathInStorage);
            DB::commit(); // YES --> finalize it

            $info = json_decode('{}');
            $info->id = $cache->id; $info->type = 'audit';

            NodeUpdateEvent::dispatch($cache->services, $info, "delete");

            return ResponseTrait::get('success', $audit);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get('error', $th->getMessage());
        }

    }

    function update_audit( Request $request )
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

            $audit = Audit::find($request->id);

            if (!$audit) throw new Exception("Audit inexistant !!");

            switch ($request->update_object)
            {
                case 'is_validated':
                {

                    if ( !$this->can_modify_valid_state($audit) )
                    {
                        if ($audit->is_validated)
                        {
                            if ($this->can_modify_node($audit))
                            {
                                if ( $this->ask_permission_for('modification', $audit) )
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
                        $audit = $this->valid_node($audit);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }
                    else
                    {
                        $audit = $this->unvalid_node($audit);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }

                    $audit->refresh();

                    ActivitiesHistoryController::record_activity($audit, $audit->is_validated ? "validate" : "invalidate");

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

            NodeUpdateEvent::dispatch($audit->services()->get(), array_map( $getId, $are_updated ), "update");
        }
        else NodeUpdateEvent::dispatch($audit->services()->get(), [$this->get_broadcast_id($audit)], "update");

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get_success($audit);

    }


}
