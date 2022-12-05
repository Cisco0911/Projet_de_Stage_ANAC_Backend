<?php

namespace App\Http\Controllers;

use App\Models\Nc;
use App\Models\User;
use App\Models\Paths;
use App\Notifications\FncReviewNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Models\NonConformite;
use App\Events\NodeUpdateEvent;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use App\Http\Traits\ServiableTrait;
use App\Http\Traits\ResponseTrait;
use Illuminate\Support\Facades\Auth;
use App\Models\operationNotification;
use Illuminate\Support\Facades\Storage;
use App\Notifications\RemovalNotification;
use Illuminate\Support\Facades\Notification;
use Thomasjohnkane\Snooze\ScheduledNotification;

class NonConformiteController extends Controller
{
    //
    use ServiableTrait;
    use ResponseTrait;

    public static function find(int $id)
    {
        $fnc = NonConformite::find($id);
        $fnc->section;
        $fnc->services;
        $fnc->path;
        $fnc->nc;
        $fnc->dossiers;
        $fnc->fichiers;
        $fnc->operation;

        return $fnc;
    }

    function format($element)
    {
        $element->services;

        $node = json_decode($element);

        // switch ($element->parent_type) {
        //     case "App\Models\Audit":
        //         $node->parent_type = 'audit';
        //         break;
        //     case "App\Models\checkList":
        //         $node->parent_type = 'checkList';
        //         break;
        //     case "App\Models\DossierPreuve":
        //         $node->parent_type = 'dp';
        //         break;
        //     case "App\Models\Nc":
        //         $node->parent_type = 'nonC';
        //         break;
        //     case "App\Models\NonConformite":
        //         $node->parent_type = 'fnc';
        //         break;
        //     case "App\Models\DossierSimple":
        //         $node->parent_type = 'ds';
        //         break;

        //     default:
        //         $node->parent_type = '';
        //         break;
        // }

        // $node->url = "http://localhost/overview_of?id=".$element->id;

        return $node;
    }


    public function get_fncs()
    {
       $fncs = NonConformite::all();

       foreach ($fncs as $key => $fnc) {
        # code...

        $fnc->services;

       }

       return $fncs;
    }

    public function add_fncs(Request $request)
    {

        DB::beginTransaction();

        $saved = true;
        $errorResponse = null;
        $new_fncs = [];

        try {
            //code...
            $request->validate([
                'nonC_id' => ['required', 'integer'],
                'services' => ['required', 'string'],
                'debut' => ['required', 'integer'],
                'fin' => ['required', 'integer'],
                'level' => ['required', 'integer'],
            ]);

//            return $request->nonC_id;
//            $date = new Date();

            $audit = Nc::find($request->nonC_id)->audit;

            $start = $request->debut;
            $end = $request->fin;
            $exceptions = json_decode($request->exceptions);

            $isException = function ($exceptions, $num)
            {
                if ( empty($exceptions) ) return false;
                foreach ($exceptions as $exception)
                {
                    if( intval($exception) == $num ) return true;
                }
                return false;
            };

            for ($i = $start ; $i < $end + 1 ; $i++)
            {
                # code...
                if (!$isException($exceptions, $i))
                {
                    $new_fnc = NonConformite::create(
                        [
                            'name' => 'FNC-'.$audit->name."-$i",
                            'level' => $request->level,
                            'nc_id' => $request->nonC_id,
                            'section_id' => $audit->section->id,

                        ]
                    );

                    $path_value = $new_fnc->nc->path->value."\\".$new_fnc->name;

                    if (!Storage::exists('public\\'.$path_value)) {
                        # code...
                        $path = Paths::create(
                            [
                                'value' => $path_value,
                                'routable_id' => $new_fnc->id,
                                'routable_type' => 'App\Models\NonConformite'
                            ]
                        );
                        if (Storage::makeDirectory("public\\".$path_value)) {

                            $services = json_decode($request->services);

                            $this->add_to_services($services, $new_fnc->id, 'App\Models\NonConformite');

//                        array_push($new_fncs, $new_fnc);
                            $new_fncs["$i"] = $new_fnc;
                        }
                        else {
                            $saved = false;
                            $errorResponse = ["msg" => "storingError", "value" => "Error : Creating folder not work, return false"];
                        }

                    }
                    else
                    {
                        $saved = false;
                        $errorResponse = "existAlready";
                    }
                }
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

            $getId = function($element){ return $element->id.'-fnc'; };

            $fnc_list = [];
            foreach ($new_fncs as $fnc)
            {
                array_push($fnc_list, $fnc);
            }

            NodeUpdateEvent::dispatch('fnc', array_map( $getId, $fnc_list ), 'add');
        }
        else
        {
            foreach ($new_fncs as $key => $new_fnc) {
                # code...
                Storage::deleteDirectory("public\\".$new_fnc->path);
            }

            DB::rollBack(); // NO --> some error has occurred undo the whole thing
        }


        $errorResponse = $errorResponse == null ? "Something went wrong !" : $errorResponse;

        return $saved ? ResponseTrait::get('success', $new_fncs) : ResponseTrait::get('error', $errorResponse) ;


    }

    function del_fnc(Request $request)
    {

        DB::beginTransaction();

        $goesWell = true;


        try
        {

            $target = NonConformite::find($request->id);

            $cache = $this->format($target);

            if(Auth::user()->validator_id == null)
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
                            'operable_type' => "App\Models\NonConformite",
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
                $new_operation->front_type = 'fnc';
                Notification::sendNow(User::find(Auth::user()->validator_id), new RemovalNotification('Non-Conformite', $new_operation, Auth::user()));
                DB::commit();
                return ResponseTrait::get('success', 'attente');
            }

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
            $info->id = $cache->id; $info->type = 'fnc';

            NodeUpdateEvent::dispatch('fnc', $info, "delete");

            return ResponseTrait::get('success', $target);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return \response(ResponseTrait::get('error', $th->getMessage()), 500);
        }

    }

    function update_fnc( Request $request )
    {

        DB::beginTransaction();

        $goesWell = true;


        try
        {

            $request->validate([
                'id' => ['required', 'integer'],
                'update_object' => ['required', 'string'],
                'new_value' => ['required'],
            ]);

            switch ($request->update_object)
            {
                case 'level':
                    NonConformite::where('id', $request->id)->update(['level' => $request->new_value]);
                    break;
                case 'review_date':
                {
                    function existing_reminder($fncId, $new_remain_time): bool
                    {
                        $review_reminders = ScheduledNotification::findByType('App\Notifications\FncReviewNotification');

                        $res = false;

                        foreach ($review_reminders as $review_reminder)
                        {
                            if ((int)$review_reminder->getNotification()->getFncId() == (int)$fncId)
                            {
                                if ($review_reminder->getNotification()->isAnticipated())
                                {
                                    $review_reminder->reschedule(
                                        Carbon::now()->addRealMilliseconds((int)$new_remain_time)->subRealMinute()
                                    );
                                    continue;
                                }
                                $review_reminder->reschedule(
                                    Carbon::now()->addRealMilliseconds((int)$new_remain_time)
                                );

                                $res = true;
                            }
                        }

                        return $res;
                    }


                    $remain_ms = json_decode($request->additional_info)->remain_ms;
                    $fnc = NonConformite::find($request->id);
                    NonConformite::where('id', $request->id)->update(['review_date' => $request->new_value]);

                    if ( !existing_reminder($request->id, $remain_ms) )
                    {
                        $inspectors = $fnc->nc->audit->users;

                        foreach ( $inspectors as $inspector )
                        {
                            ScheduledNotification::create(
                                $inspector, // Target
                                new FncReviewNotification($request->id), // Notification
                                Carbon::now()->addRealMilliseconds((int)$remain_ms) // Send At
                            );

                            ScheduledNotification::create(
                                $inspector, // Target
                                new FncReviewNotification($request->id, true), // Notification
                                Carbon::now()->addRealMilliseconds((int)$remain_ms)->subRealMinute() // Send At
                            );

                        }
                    }
//                    return "nooooooooothing";

                    break;
                }
                default:
                    return ResponseTrait::get('success', 'Nothing was done');
            }

        }
        catch (\Throwable $th) {
            //throw $th;
            $goesWell = false;
        }

        if($goesWell)
        {
            DB::commit(); // YES --> finalize it

            // $getId = function($element){ return $element->id.'-fnc'; }; array_map( $getId, $request )

            NodeUpdateEvent::dispatch('fnc', [$request->id.'-fnc'], "update");

            return ResponseTrait::get('success', null);
        }
        else
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return \response(ResponseTrait::get('error', $th->getMessage()), 500);
        }

    }

}
