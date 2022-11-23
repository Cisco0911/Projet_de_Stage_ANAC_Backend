<?php

namespace App\Http\Controllers;

use App\Http\Traits\ResponseTrait;
use App\Models\Nc;
use App\Models\User;
use App\Models\Audit;
use App\Models\Paths;
use App\Models\checkList;
use App\Models\Serviable;
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

    public static function find(int $id)
    {
        $audit = Audit::find($id);
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

        return $audit;
    }

    function format($element)
    {
        $element->services;
        $element->user;

        $node = json_decode($element);

        return $node;
    }


    public function get_audits()
    {
       $audits = Audit::all();

       foreach ($audits as $key => $audit) {
        # code...

        $audit->services;
        $audit->user;

       }

       return $audits;
    }

    public function add_audit(Request $request)
    {
        DB::beginTransaction();

        $saved = true;
        $errorResponse = null;
        $audit_family = [];

        try {
            //code...

            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'section_id' => ['required', 'integer'],
                'services' => ['required', 'string'],
                'ra_id' => ['required', 'integer'],
            ]);

            $new_audit = null;

            $new_audit = Audit::create(
                [
                    'name' => $request->name,
                    'section_id' => $request->section_id,
                    'user_id' => $request->ra_id,
                ]
            );

            $audit_path = $new_audit->section->path->value.'\\'.$new_audit->name;



//            $new_checkList = checkList::create( ['audit_id'=> $new_audit->id, 'section_id' => $request->section_id] );
            $new_checkList = $new_audit->checklist()->create( [ 'section_id' => $request->section_id ] );
            $new_checkList->name = 'checkList';
            $new_checkList->sub_type = 'checkList';

            $new_dp = $new_audit->dossier_preuve()->create( [ 'section_id' => $request->section_id ] );
            $new_dp->name = 'Dossier Preuve';
            $new_dp->sub_type = 'dp';

            $new_nonC = $new_audit->nc()->create( [ 'section_id' => $request->section_id ] );
            $new_nonC->name = 'NC';
            $new_nonC->sub_type = 'nonC';


            $pathAudit = Paths::create(
                [
                    'value' => $audit_path,
                    'routable_id' => $new_audit->id,
                    'routable_type' => 'App\Models\Audit'
                ]
            );

            $pathCheckList = Paths::create(
                [
                    'value' => $pathAudit->value.'\\CheckList',
                    'routable_id' => $new_checkList->id,
                    'routable_type' => 'App\Models\checkList'
                ]
            );

            $pathDp = Paths::create(
                [
                    'value' => $pathAudit->value.'\\Dossier Preuve',
                    'routable_id' => $new_dp->id,
                    'routable_type' => 'App\Models\DossierPreuve'
                ]
            );

            $pathNc = Paths::create(
                [
                    'value' => $pathAudit->value.'\\NC',
                    'routable_id' => $new_nonC->id,
                    'routable_type' => 'App\Models\Nc'
                ]
            );

//            return $pathCheckList->value;

            if (Storage::makeDirectory("public\\".$pathCheckList->value) && Storage::makeDirectory("public\\".$pathDp->value) && Storage::makeDirectory("public\\".$pathNc->value)) {


                $services = json_decode($request->services);

                $this->add_to_services($services, $new_audit->id, 'App\Models\Audit');
                $this->add_to_services($services, $new_checkList->id, 'App\Models\checkList');
                $this->add_to_services($services, $new_dp->id, 'App\Models\DossierPreuve');
                $this->add_to_services($services, $new_nonC->id, 'App\Models\Nc');

                array_push($audit_family, $new_audit, $new_checkList, $new_dp, $new_nonC);



            }
            else
            {
                $saved = false;
                $errorResponse = ["msg" => "storingError", "value" => "Error : Creating folder not work, return false"];
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

            $getId = function($element){ if($element->sub_type != null) return $element->id.'-'.$element->sub_type; return $element->id.'-audit'; };

            NodeUpdateEvent::dispatch('audit', array_map( $getId, $audit_family ), 'add');
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing
        }

        // DB::endTransaction();

        $errorResponse = $errorResponse == null ? "Something went wrong !" : $errorResponse;

        return $saved ? ResponseTrait::get('success', AuditController::find($new_audit->id)) : ResponseTrait::get('error', $errorResponse) ;

    }

    function del_audit(Request $request)
    {

        DB::beginTransaction();

        $goesWell = true;


        try {

            $target = Audit::find($request->id);

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
                            'operable_type' => "App\Models\Audit",
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
                $new_operation->front_type = 'audit';
                Notification::sendNow(User::find(Auth::user()->validator_id), new RemovalNotification('Audit', $new_operation, Auth::user()));
                DB::commit();
                return ResponseTrait::get('success', 'attente');
            }

        } catch (\Throwable $th) {
            //throw $th;
            $goesWell = false;
        }

        if($goesWell)
        {
            Storage::deleteDirectory($pathInStorage);
            DB::commit(); // YES --> finalize it
            NodeUpdateEvent::dispatch('audit', $cache, "delete");

            return ResponseTrait::get('success', $target);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return \response(ResponseTrait::get('error', $th->getMessage()), 500);
        }

    }


}
