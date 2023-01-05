<?php

namespace App\Http\Controllers;

use App\Events\AuthUserUpdate;
use App\Http\Traits\ResponseTrait;
use App\Models\User;
use App\Notifications\InfoNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use PHPUnit\Util\Exception;

class UserController extends Controller
{
    //

    use ResponseTrait;

    public static function find(int $id)
    {
        $users = User::find($id);
        $users->services;
        $users->audits;
        $users->audits_belonging_to;
//        $users->operationInQueue;
//        $users->operationInQueue;

        return $users;
    }

    public function get_users()
    {
        $users = User::all();

        foreach ($users as $key => $user) {
            # code...

            $user->services;

        }

        return $users;
    }

    public function markAsRead(Request $request)
    {

        $request->validate([
            'notif_ids' => ['required', 'array'],
        ]);

        foreach ( $request->notif_ids as $notif_id )
        {
            foreach (Auth::user()->unreadNotifications as $notification)
            {
                if ($notification->id == $notif_id)
                {
                    $notification->markAsRead();
                    break;
                }
            }
        }

        AuthUserUpdate::dispatch(Auth::user());

        return 'ok';

    }

    public function handle_permission_response(Request $request)
    {

        DB::beginTransaction();

        $goes_well = true;

        try
        {

            $request->validate([
                'demand_id' => ['required', 'string'],
                'approved' => ['required', 'integer'],
            ]);

            $permission_notification = Auth::user()->notifications()->find($request->demand_id);

            if ($permission_notification->data["operation"] == 'deletion')
            {

                $response = $request->approved ? "accordée" : "rejetée";

                switch ($permission_notification->data["model"]) {
                    case "App\Models\Audit":
                        $attachment = new \stdClass();
                        $attachment->Audit = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $audit = new AuditController();
                            $res = $audit->del_audit(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                    ]
                                )
                            );
                        }

                        break;
                    case "App\Models\NonConformite":
                        $attachment = new \stdClass();
                        $attachment->Fnc = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $fnc = new NonConformiteController();
                            $res = $fnc->del_fnc(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                    ]
                                )
                            );
                        }

                        break;
                    case "App\Models\DossierSimple":
                        $attachment = new \stdClass();
                        $attachment->Dossier = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $folder = new DossierSimpleController();
                            $res = $folder->del_folder(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                    ]
                                )
                            );
                        }

                        break;
                    case "App\Models\Fichier":
                        $attachment = new \stdClass();
                        $attachment->Fichier = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $file = new FichierController();
                            $res = $file->del_file(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                    ]
                                )
                            );
                        }

                        break;

                    default:
                        throw new \Exception('Element inconnu', -1);
                }


                if (!empty($res) && $res['statue'] == 'error')
                {
                    throw new Exception($res['data']->msg);
                }

                $this->inform(
                    new Request(
                        [
                            'to' => $permission_notification->data["from_id"],
                            'object' => "Réponse de la demande d'autorisation",
                            'msg' => "Demande de suppression $response !",
                            'attachment' => json_encode($attachment),
                        ]
                    )
                );

            }
            elseif ($permission_notification->data["operation"] == 'modification')
            {

                $response = $request->approved ? "accordée" : "rejetée";

                switch ($permission_notification->data["model"]) {
                    case "App\Models\Audit":
                        $attachment = new \stdClass();
                        $attachment->Audit = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $audit = new AuditController();
                            $res = $audit->update_audit(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                        'update_object' => 'is_validated',
                                        'new_value' => 0,
                                    ]
                                )
                            );
                        }

                        break;
                    case "App\Models\checkList":
                        $attachment = new \stdClass();
                        $attachment->checkList = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $checkList = new CheckListController();
                            $res = $checkList->update_checkList(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                        'update_object' => 'is_validated',
                                        'new_value' => 0,
                                    ]
                                )
                            );
                        }

                        break;
                    case "App\Models\DossierPreuve":
                        $attachment = new \stdClass();
                        $attachment->Dossier_preuve = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $dp = new DossierPreuveController();
                            $res = $dp->update_dp(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                        'update_object' => 'is_validated',
                                        'new_value' => 0,
                                    ]
                                )
                            );
                        }

                        break;
                    case "App\Models\Nc":
                        $attachment = new \stdClass();
                        $attachment->Nc = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {$nc = new NcController();
                            $res = $nc->update_nc(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                        'update_object' => 'is_validated',
                                        'new_value' => 0,
                                    ]
                                )
                            );
                        }


                        break;
                    case "App\Models\NonConformite":
                        $attachment = new \stdClass();
                        $attachment->Fnc = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $fnc = new NonConformiteController();
                            $res = $fnc->update_fnc(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                        'update_object' => 'is_validated',
                                        'new_value' => 0,
                                    ]
                                )
                            );
                        }

                        break;
                    case "App\Models\DossierSimple":
                        $attachment = new \stdClass();
                        $attachment->Dossier = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $folder = new DossierSimpleController();
                            $res = $folder->update_folder(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                        'update_object' => 'is_validated',
                                        'new_value' => 0,
                                    ]
                                )
                            );
                        }

                        break;
                    case "App\Models\Fichier":
                        $attachment = new \stdClass();
                        $attachment->Fichier = $permission_notification->data["node_name"];

                        if ($request->approved)
                        {
                            $file = new FichierController();
                            $res = $file->update_file(
                                new Request(
                                    [
                                        'id' => $permission_notification->data["node_id"],
                                        'update_object' => 'is_validated',
                                        'new_value' => 0,
                                    ]
                                )
                            );
                        }

                        break;

                    default:
                        throw new \Exception('Element inconnu', -1);
                }
            }


            if (!empty($res) && $res['statue'] != 'success')
            {
                throw new Exception($res['data']->msg);
            }

            $this->inform(
                new Request(
                    [
                        'to' => $permission_notification->data["from_id"],
                        'object' => "Réponse de la demande d'autorisation",
                        'msg' => "Demande de modification $response !",
                        'attachment' => json_encode($attachment),
                    ]
                )
            );

            $permission_notification->delete();
        }
        catch (\Throwable $th)
        {
            DB::rollBack();
            return ResponseTrait::get_error($th);
        }

        DB::commit();

        return ResponseTrait::get('success', 'very good');

    }

    public function inform(Request $request)
    {
        $request->validate([
            'to' => ['required', 'integer'],
            'object' => ['required', 'string'],
            'msg' => ['required', 'string', 'max:255'],
            'attachment' => ['nullable', 'json'],
        ]);

        $to_user = self::find($request->to);

        $to_user->notify( new InfoNotification($request->object, $request->msg, $request->attachment, Auth::user()) );

    }

}
