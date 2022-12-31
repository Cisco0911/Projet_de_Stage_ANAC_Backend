<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\operationNotification;
use App\Notifications\InfoNotification;
use Illuminate\Support\Facades\Notification;

class OperationNotificationController extends Controller
{
    //



    public function notify_response(Request $request)
    {
        $request->validate([
            'to' => ['required', 'integer'],
            'object' => ['required', 'string'],
            'value' => ['required', 'string', 'max:255'],
            'from' => ['required', 'string'],
        ]);

        $msg =
        [
            'object' => $request->object,
            'value' => $request->value,
            'attachment' => json_decode($request->attachment),
        ];

        if( $msg['object'] == 'rejected' ) operationNotification::where([ 'operable_id' =>  $msg['attachment']->operable->id])->delete();

        Notification::sendNow(User::find($request->to), new InfoNotification($msg, json_decode($request->from)) );

        return $msg['attachment'];
    }

}
