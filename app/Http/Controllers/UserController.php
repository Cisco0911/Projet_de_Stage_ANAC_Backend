<?php

namespace App\Http\Controllers;

use App\Events\AuthUserUpdate;
use App\Http\Traits\ResponseTrait;
use App\Models\User;
use App\Notifications\InfoNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

                if ($response)
                {
                    $lol = 'p';
//                    switch ($permission_notification->data["model"]) {
//                        case "App\Models\Audit":
//                            $audit = new AuditController();
//                            $audit->del_audit(
//                                new Request(
//                                    [
//                                        'id' => $permission_notification->data["node_id"],
//                                    ]
//                                )
//                            );
//                            break;
//                        case "App\Models\NonConformite":
//                            $fnc = new NonConformiteController();
//                            $fnc->del_fnc(
//                                new Request(
//                                    [
//                                        'id' => $permission_notification->data["node_id"],
//                                    ]
//                                )
//                            );
//                            break;
//                        case "App\Models\DossierSimple":
//                            $folder = new DossierSimpleController();
//                            $folder->del_folder(
//                                new Request(
//                                    [
//                                        'id' => $permission_notification->data["node_id"],
//                                    ]
//                                )
//                            );
//                            break;
//                        case "App\Models\Fichier":
//                            $file = new FichierController();
//                            $file->del_file(
//                                new Request(
//                                    [
//                                        'id' => $permission_notification->data["node_id"],
//                                    ]
//                                )
//                            );
//                            break;
//
//                        default:
//                            throw new \Exception('Element inconnu', -1);
//                    }
                }

                $attachment = new \stdClass();
                $attachment->node_name = $permission_notification->data["node_name"];
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
            else
            {
                throw new \Exception("else modificatio", -2);
                switch ($permission_notification->data["model"]) {
                    case "App\Models\Audit":
                        $audit = new AuditController();
                        $audit->update_audit(
                            new Request(
                                [
                                    'id' => $permission_notification->data["node_id"],
                                    'update_object' => 'is_validated',
                                    'new_value' => 0,
                                ]
                            )
                        );
                        break;
                    case "App\Models\checkList":
                        $checkList = new CheckListController();
                        $checkList->update_checkList(
                            new Request(
                                [
                                    'id' => $permission_notification->data["node_id"],
                                    'update_object' => 'is_validated',
                                    'new_value' => 0,
                                ]
                            )
                        );
                        break;
                    case "App\Models\DossierPreuve":
                        $dp = new DossierPreuveController();
                        $dp->update_dp(
                            new Request(
                                [
                                    'id' => $permission_notification->data["node_id"],
                                    'update_object' => 'is_validated',
                                    'new_value' => 0,
                                ]
                            )
                        );
                        break;
                    case "App\Models\Nc":
                        $nc = new NcController();
                        $nc->update_nc(
                            new Request(
                                [
                                    'id' => $permission_notification->data["node_id"],
                                    'update_object' => 'is_validated',
                                    'new_value' => 0,
                                ]
                            )
                        );
                        break;
                    case "App\Models\NonConformite":
                        $fnc = new NonConformiteController();
                        $fnc->update_fnc(
                            new Request(
                                [
                                    'id' => $permission_notification->data["node_id"],
                                    'update_object' => 'is_validated',
                                    'new_value' => 0,
                                ]
                            )
                        );
                        break;
                    case "App\Models\DossierSimple":
                        $folder = new DossierSimpleController();
                        $folder->update_folder(
                            new Request(
                                [
                                    'id' => $permission_notification->data["node_id"],
                                    'update_object' => 'is_validated',
                                    'new_value' => 0,
                                ]
                            )
                        );
                        break;
                    case "App\Models\Fichier":
                        $file = new FichierController();
                        $file->update_file(
                            new Request(
                                [
                                    'id' => $permission_notification->data["node_id"],
                                    'update_object' => 'is_validated',
                                    'new_value' => 0,
                                ]
                            )
                        );
                        break;

                    default:
                        throw new \Exception('Element inconnu', -1);
                }
            }

//            $permission_notification->delete();
        }
        catch (\Throwable $th)
        {
            DB::rollBack();
            return ResponseTrait::get('error', $th->getMessage());
        }

        DB::rollBack();

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
