<?php

namespace App\Http\Controllers;

use App\Http\Traits\NodeTrait;
use App\Models\Nc;
use App\Models\User;
use App\Models\Paths;
use App\Notifications\FncReviewNotification;
use Carbon\Carbon;
use Exception;
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
    use NodeTrait;

    public static function find(int $id)
    {
        $fnc = NonConformite::find($id);
        $fnc->section;
        $fnc->services;
        $fnc->path;
        $fnc->nc_folder;
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

        $existing_fnc = [];
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

            $parent = $this->find_node($request->nonC_id, "App\Models\Nc");

            if (empty($parent))
            {
                throw new Exception("Parent inexistant.", -4);
            }

            $feasible = $this->can_modify_node($parent);

            if( $feasible != 2 ) throw new Exception("Vous n'avez pas les droits nécessaires\nSi le parent est validé, veuillez faire une demande d'autorisation de modification", -3);

//            return $request->nonC_id;
//            $date = new Date();

            $audit = $parent->audit;

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
                    $new_fnc = $parent->fncs()->create(
                        [
                            'name' => 'FNC-'.$audit->name."-$i",
                            'level' => $request->level,
                            'section_id' => $audit->section->id,
                            'is_validated' => $parent->is_validated,
                            'validator_id' => $parent->validator_id,

                        ]
                    );

                    $path_value = $new_fnc->nc_folder->path->value."\\".$new_fnc->name;

                    if (!Paths::where([ 'value' => $path_value ])->exists()) {
                        # code...
                        $path = $new_fnc->path()->create(
                            [
                                'value' => $path_value,
                            ]
                        );
                        if (Storage::makeDirectory("public\\".$path_value)) {

                            $services = json_decode($request->services);

                            $this->add_to_services($services, $new_fnc->id, 'App\Models\NonConformite');

//                        array_push($new_fncs, $new_fnc);
                            $new_fncs["$i"] = $new_fnc;
                        }
                        else {
                            throw new Exception('Erreur de stockage: La création en stockage a échoué.', 1);
                        }

                    }
                    else
                    {
                        array_push($existing_fnc, $new_fnc->name);
                        $new_fncs["$i"] = Paths::where([ 'value' => $path_value ])->first()->routable;
                        $new_fnc->delete();
                    }
                }
            }


        }
        catch (\Throwable $th)
        {
            foreach ($new_fncs as $key => $new_fnc) {
                # code...
                Storage::deleteDirectory("public\\".$new_fnc->path);
            };
            DB::rollBack();
            return ResponseTrait::get_error($th);
        }

        DB::commit(); // YES --> finalize it

        $getId = function($element){ return $element->id.'-fnc'; };

        $fnc_list = [];
        foreach ($new_fncs as $fnc)
        {
            if ( !array_search($fnc->name, $existing_fnc) ) array_push($fnc_list, $fnc);
        }

        NodeUpdateEvent::dispatch('fnc', array_map( $getId, $fnc_list ), 'add');

        if (!empty($existing_fnc)) $new_fncs['existing_fnc'] = $existing_fnc;

        return ResponseTrait::get_success($new_fncs);


    }

    function del_fnc(Request $request)
    {

        DB::beginTransaction();

        $goesWell = true;


        try {

            $target = NonConformite::find($request->id);

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

                    return ResponseTrait::get_info("Demande de permission");
                }
            }
            else
            {
                throw new Exception("Vous n'avez pas les droits nécessaires");
            }

        }
        catch (\Throwable $th)
        {
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

    protected function existing_reminder($fncId, $new_remain_time): bool
    {
        $review_reminders = ScheduledNotification::findByMeta("fncId", $fncId);

        $res = false;

        $already = [];

        foreach ($review_reminders as $review_reminder)
        {
            if ($review_reminder->isSent()) continue;

            if ($review_reminder->getNotification()->isAnticipated())
            {
                $review_reminder->reschedule(
                    Carbon::now()->addRealMilliseconds((int)$new_remain_time)->subRealMinute()
                );

                array_push($already, -$review_reminder->getTargetId());

                continue;
            }

            $review_reminder->reschedule(
                Carbon::now()->addRealMilliseconds((int)$new_remain_time)
            );

            array_push($already, $review_reminder->getTargetId());

            $res = true;
        }

        foreach ($review_reminders as $review_reminder)
        {
//            if ((int)$review_reminder->getNotification()->getFncId() == (int)$fncId)
//            {
//                if ($review_reminder->getNotification()->isAnticipated())
//                {
//                    $review_reminder->reschedule(
//                        Carbon::now()->addRealMilliseconds((int)$new_remain_time)->subRealMinute()
//                    );
//                    continue;
//                }
//                $review_reminder->reschedule(
//                    Carbon::now()->addRealMilliseconds((int)$new_remain_time)
//                );
//
//                $res = true;
//            }
            if (!$review_reminder->isSent()) continue;

            if ( $review_reminder->getNotification()->isAnticipated() && !in_array(-$review_reminder->getTargetId(), $already) )
            {
                $review_reminder->reschedule(
                    Carbon::now()->addRealMilliseconds((int)$new_remain_time)->subRealMinute(),
                    true
                );

                array_push($already, -$review_reminder->getTargetId());

                continue;
            }

            if ( !in_array($review_reminder->getTargetId(), $already) )
            {

                $review_reminder->reschedule(
                    Carbon::now()->addRealMilliseconds((int)$new_remain_time),
                    true
                );

                array_push($already, $review_reminder->getTargetId());

            }

            $res = true;
        }

        return $res;
    }

    function update_fnc( Request $request )
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

            $fnc = NonConformite::find($request->id);

            if (empty($fnc)) throw new Exception("Fnc inexistant !!");


            switch ($request->update_object)
            {
                case 'level':

                    if($this->can_modify_node($fnc) !== 2) throw new Exception("Vous n'avez pas les droits nécessaires", -2);

                    $fnc->level = $request->new_value;
                    $fnc->push();
                    $fnc->refresh();

                    break;
                case 'review_date':
                {

                    if($this->can_modify_node($fnc) !== 2) throw new Exception("Vous n'avez pas les droits nécessaires", -2);

                    $remain_ms = json_decode($request->additional_info)->remain_ms;
                    $fnc->review_date = $request->new_value;
                    $fnc->push();
                    $fnc->refresh();

                    if ( !$this->existing_reminder($fnc->id, $remain_ms) && !$fnc->isClosed )
                    {
                        $inspectors = $fnc->nc_folder->audit->users;

                        foreach ( $inspectors as $inspector )
                        {
                            ScheduledNotification::create(
                                $inspector, // Target
                                new FncReviewNotification($fnc->id), // Notification
                                Carbon::now()->addRealMilliseconds((int)$remain_ms), // Send At
                                ["fncId" => $fnc->id] //meta data
                            );
//                            $review_fnc_notification->scheduleAgainAt( Carbon::now()->addRealMilliseconds((int)$remain_ms)->subRealMinute() );

                            ScheduledNotification::create(
                                $inspector, // Target
                                new FncReviewNotification($request->id, true), // Notification
                                Carbon::now()->addRealMilliseconds((int)$remain_ms)->subRealMinute(), // Send At
                                ["fncId" => $fnc->id] //meta data
                            );

                        }
                    }
//                    return "nooooooooothing";

                    break;
                }
                case 'is_validated':
                {

                    if ( !$this->can_modify_valid_state($fnc) )
                    {
                        if ($fnc->is_validated)
                        {
                            if ($this->can_modify_node($fnc))
                            {
                                if ( $this->ask_permission_for('modification', $fnc) )
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
                        $fnc = $this->valid_node($fnc);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }
                    else
                    {
                        $fnc = $this->unvalid_node($fnc);

                        $are_updated = $GLOBALS['to_broadcast'];
                    }

                    break;
                }
                case 'isClosed':

                    if ($fnc->audit_folder()->user->id != Auth::id()) throw new Exception("Vous ne pouvez pas clôturer cette non-conformité car vous en êtes pas le responsable !!");

                    if($this->can_modify_node($fnc) !== 2) throw new Exception("Vous n'avez pas les droits nécessaires", -2);

                    $fnc->isClosed = $request->new_value;
                    $fnc->push();
                    $fnc->refresh();
                    break;
                default:
                    DB::rollBack();

                    $GLOBALS['to_broadcast'] = [];

                    return ResponseTrait::get_success($fnc);
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

            NodeUpdateEvent::dispatch('fnc', array_map( $getId, $are_updated ), "update");
        }
        else NodeUpdateEvent::dispatch('fnc', [$this->get_broadcast_id($fnc)], "update");

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get_success($fnc);

    }

}
