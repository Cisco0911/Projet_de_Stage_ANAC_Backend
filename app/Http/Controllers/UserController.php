<?php

namespace App\Http\Controllers;

use App\Events\AuthUserUpdate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    //


    public static function find(int $id)
    {
        $users = User::find($id);
        $users->services;
        $users->audits;
        $users->audits_belonging_to;
        $users->operationInQueue;
        $users->operationInQueue;

        return $users;
    }

    public function get_users()
    {
        $users = User::all();

        foreach ($users as $key => $users) {
            # code...

            $users->services;

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

}
