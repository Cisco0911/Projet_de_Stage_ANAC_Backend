<?php

namespace App\Http\Controllers;

use App\Events\AuthUserUpdate;
use App\Http\Traits\ResponseTrait;
use App\Models\User;
use App\Notifications\InfoNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use PHPUnit\Util\Exception;

class UserController extends Controller
{
    //

    use ResponseTrait;

    public static function user()
    {
        $authUser = Auth::user();
        if ($authUser instanceof User)
        {
            # code...

            $format = function($node)
            {

                switch ($node->operable_type) {
                    case "App\Models\Audit":
                        $node->front_type = 'audit';
                        $node->node_type = 'Audit';
                        break;
                    case "App\Models\checkList":
                        $node->front_type = 'checkList';
                        $node->node_type = 'CheckList';
                        break;
                    case "App\Models\DossierPreuve":
                        $node->front_type = 'dp';
                        $node->node_type = 'Dossier Preuve';
                        break;
                    case "App\Models\Nc":
                        $node->front_type = 'nonC';
                        $node->node_type = 'NC';
                        break;
                    case "App\Models\NonConformite":
                        $node->front_type = 'fnc';
                        $node->node_type = 'FNC';
                        break;
                    case "App\Models\DossierSimple":
                        $node->front_type = 'ds';
                        $node->node_type = 'Dossier';
                        break;
                    case "App\Models\Fichier":
                        $node->front_type = 'f';
                        $node->node_type = 'Fichier';
                        break;

                    default:;
                        break;
                }

                return json_decode($node);
            };



            $authUser->services = $authUser->services()->get();

            $authUser->unread_review_notifications = $authUser->notifications()
                ->unread()
                ->where('type', 'App\Notifications\FncReviewNotification')
                ->get();
            $authUser->asking_permission_notifications = $authUser->notifications()
                ->unread()
                ->where('type', 'App\Notifications\AskPermission')
                ->get();
            $authUser->readNotifications;
        }

        return $authUser;
    }

    public static function find(int $id) : User | null
    {
        $user = User::find($id);
        if ($user)
        {
            $user->services;
            $user->audits;
            $user->audits_belonging_to;
        }
//        $users->operationInQueue;
//        $users->operationInQueue;

        return $user;
    }

    public function get_users()
    {
        $users = User::where("id", "!=", 0)->get();

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
            }

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

    public function update_name(Request $request)
    {
        DB::beginTransaction();

        try {

            $user = $request->user();
            $request->validate([
                'name' => [
                    'required',
                    'string',
                    Rule::unique("users")->ignore($user->id)
                ],
            ]);

            $user->name = $request->name;

            $user->push();
            $user->refresh();

        }
        catch (\Throwable $th)
        {
            DB::rollBack();
            return ResponseTrait::get_error($th);
        }

        DB::commit();
        return ResponseTrait::get_success($user);
    }

    public function update_second_name(Request $request)
    {
        DB::beginTransaction();

        try {

            $user = $request->user();
            $request->validate([
                'second_name' => [
                    'required',
                    'string',
                    Rule::unique("users")->ignore($user->id)
                ],
            ]);

            $user->second_name = $request->second_name;

            $user->push();
            $user->refresh();

        }
        catch (\Throwable $th)
        {
            DB::rollBack();
            return ResponseTrait::get_error($th);
        }

        DB::commit();
        return ResponseTrait::get_success($user);
    }

    public function update_email(Request $request)
    {
        DB::beginTransaction();

        try {

            $user = $request->user();
            $request->validate([
                'email' => [
                    'required',
                    'string',
                    'email',
                    'max:255',
                    Rule::unique('users')->ignore($user->id),
                ],
            ]);
//            Validator::make($request->all(), [
//                'email' => [
//                    'required',
//                    'string',
//                    'email',
//                    'max:255',
//                    Rule::unique('users')->ignore($user->id),
//                ],
//            ]);

            $user->email = $request->email;

            $user->push();
            $user->refresh();

        }
        catch (\Throwable $th)
        {
            DB::rollBack();
            return ResponseTrait::get_error($th);
        }

        DB::commit();
        return ResponseTrait::get_success($user);
    }

}
