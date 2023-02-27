<?php

namespace App\Http\Controllers;

use App\Events\AuthUserUpdate;
use App\Events\NodeUpdateEvent;
use App\Http\Traits\NodeTrait;
use App\Http\Traits\ResponseTrait;
use App\Models\Fichier;
use App\Models\NonConformite;
use App\Models\Notification;
use App\Models\Section;
use App\Models\Service;
use App\Models\User;
use App\Notifications\NewUserNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Thomasjohnkane\Snooze\ScheduledNotification;

class AdministratorController extends Controller
{
    //
    use NodeTrait;


//    private $activities_controler;


    protected function format($element)
    {
        if ($element instanceof User)
        {
            $element->services;

        }
        elseif ($element instanceof Section)
        {
            $element->services;

        }
        elseif ($element instanceof DatabaseNotification)
        {
//            return $element;
            if ($element->type === 'App\Notifications\NewUserNotification')
            {
                $element = User::find($element->data["user_id"]);
                $element->services;
//            return "lol";
            }

        }
        else $element = get_class($element);

        return $element;
    }


    public function get_data(Request $request)
    {

        $data = new \stdClass();

        $data->auth = Auth::user();

        $sections = Section::all();
        foreach ($sections as $key => $section) $sections[$key] = $this->format($section);
        $data->sections = $sections;

        $data->services = Service::all();

        $users = User::where("id", "!=", 0)->get();
        foreach ($users as $key => $user) $users[$key] = $this->format($user);
        $data->users = $users;

        $new_user_notifications = Auth::user()->notifications()
            ->unread()
            ->where('type', 'App\Notifications\NewUserNotification')
//            ->where('data->user_id', 8)
            ->get();
        foreach ($new_user_notifications as $key => $new_user_notification) $new_user_notifications[$key] = $this->format($new_user_notification);
        $data->new_users = $new_user_notifications;

        $data->history = ActivitiesHistoryController::get_history();

        return $data;
    }


    protected function safe_service_change($user, $new_services_names)
    {
        $new_service_ids = [];

        foreach ($new_services_names as $service_name)
        {
            $service_id = Service::where("name", $service_name)->first()->id;

            array_push( $new_service_ids, $service_id );
        }

        if ( !empty( $user->audits ) && ( count( $user->audits ) > 0 ) )
        {
            foreach ($user->audits as $audit)
            {
                $node_services_ids = array_map( function ($service){ return $service["id"]; }, $audit->services->toArray() );

                $intersect = array_intersect( $new_service_ids, $node_services_ids );

//                throw new \Exception(json_encode($intersect));
                if ( !count($intersect) ) throw new \Exception("L'utilisateur est RA d'un audit dans un service qu'il quitte, priere de faire une transmission de role d'abord !!");

            }

//                return $from_user->audits;
        }
        if ( !empty( $from_user->audits_belonging_to ) && ( count( $from_user->audits_belonging_to ) > 0 ) )
        {

            $from_user->audits_belonging_to()->detach();

//                return $from_user->audits_belonging_to;
        }

        $validated_nodes = $user->validated_nodes();
        if ( !empty( $validated_nodes ) && ( count( $validated_nodes ) > 0 ) )
        {
            foreach ($validated_nodes as $validated_node)
            {

                $node_services_ids = array_map( function ($service){ return $service["id"]; }, $validated_node->services->toArray() );

                $intersect = array_intersect( $new_service_ids, $node_services_ids );

//                throw new \Exception(json_encode($intersect));
                if ( !count($intersect) ) $this->unvalid_node($validated_node);
            }

//                return $user->validated_audits;
        }

    }

    public function update_user(Request $request)
    {
        DB::beginTransaction();

        $GLOBALS['to_broadcast'] = [];


        try
        {

            $request->validate([
                'id' => ['required', 'integer'],
                'update_object' => ['required', 'string'],
                'new_value' => ['required'],
            ]);

            $user = User::find($request->id);

            if (!$user) throw new Exception("Utilisateur inexistant !!");

            switch ($request->update_object)
            {
                case 'right_lvl':
                {
                    $user->right_lvl = (int)$request->new_value;

                    $validated_nodes = $user->validated_nodes();
                    if ( !empty( $validated_nodes ) && ( count( $validated_nodes ) > 0 ) )
                    {
                        foreach ($validated_nodes as $validated_node)
                        {
                            $this->unvalid_node($validated_node);
                        }

//                        return $validated_nodes;
                    }

                    $user->push();
                    $user->refresh();

                    break;
                }
                case 'services':
                {
//                    return $request->new_value;
                    $service_names = json_decode($request->new_value);

                    $this->safe_service_change($user, $service_names);

                    $user->services()->detach();
                    foreach ($service_names as $service_name)
                    {
                        $service_id = Service::where("name", $service_name)->first()->id;

                        $user->services()->attach($service_id);
                    }

                    $user->push();
                    $user->refresh();

                    break;
                }
                default:
                    DB::rollBack();

                    return ResponseTrait::get_info('Nothing was done');
            }


            Notification::where('type', 'App\Notifications\NewUserNotification')
                ->where('data->user_id', $user->id)
                ->delete();

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        DB::commit();

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get_success($user);

    }

    public function delete_user(Request $request)
    {

        DB::beginTransaction();
        $GLOBALS['to_broadcast'] = [];

        try
        {

            $request->validate([
                'id' => ['required', 'integer'],
            ]);

            $user = User::find( $request->id );

            foreach ($user->validated_nodes() as $validated_node) $this->unvalid_node($validated_node);

            $user->delete();

//            DB::rollBack();
//            return ResponseTrait::get_info("ca a marche");

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            $GLOBALS['to_broadcast'] = [];
            return ResponseTrait::get_error($th);
        }

        DB::commit();

        $GLOBALS['to_broadcast'] = [];
        return ResponseTrait::get_success("GOOD !!");
    }


    public function role_exchange(Request $request)
    {
        DB::beginTransaction();


        try {

            $request->validate([
                'from_id' => ['required', 'integer'],
                'to_id' => ['required', 'integer'],
                'exchange' => ['required', 'bool'],
            ]);

            $from_user = User::find($request->from_id);
            $to_user = User::find($request->to_id);

//            return [ array_search( 1, array_map( function ($service){ return $service["id"]; }, $to_user->services->toArray() ) ), array_map( function ($service){ return $service["id"]; }, $to_user->services->toArray() )];

            if ($request->exchange)
            {
                DB::statement('SET FOREIGN_KEY_CHECKS=0;');

                $from_user->id = 1;

//                DB::update('update users set id = ? where id = ?', [18000000000000000000, $from_user->id]);

                $from_right = $from_user->right_lvl;

                $from_user->right_lvl = $to_user->right_lvl;

                $from_user->push();
//                $from_user->refresh();

                $to_user->id = $request->from_id;

//                DB::update('update users set id = ? where id = ?', [$request->from_id, $to_user->id]);

                $to_user->right_lvl = $from_right;

                $to_user->push();
                $to_user->refresh();

                $from_user->id = $request->to_id;

//                DB::update('update users set id = ? where id = ?', [$request->to_id, 18000000000000000000]);

                $from_user->push();
//                $from_user->refresh();

                DB::statement('SET FOREIGN_KEY_CHECKS=1;');

                $new_from_user = User::find($request->to_id);
                $new_to_user = User::find($request->from_id);

//                Notification::where("notifiable_id", $new_from_user->id)
//                    ->orWhere("notifiable_id", $new_to_user->id)
//                    ->delete();
            }
            else
            {
                if ( !empty( $from_user->audits ) && ( count( $from_user->audits ) > 0 ) )
                {
                    foreach ($from_user->audits as $audit)
                    {
                        $audit->user_id = $to_user->id;

                        $audit->push();
                    }

//                return $from_user->audits;
                }
                if ( !empty( $from_user->audits_belonging_to ) && ( count( $from_user->audits_belonging_to ) > 0 ) )
                {
                    foreach ($from_user->audits_belonging_to as $audit)
                    {
                        $to_user->audits_belonging_to()->attach($audit->id);
                    }

                    $from_user->audits_belonging_to()->detach();

//                return $from_user->audits_belonging_to;
                }

                $validated_nodes = $from_user->validated_nodes();

//                DB::rollBack();
//                return $validated_nodes;
                if ( !empty( $validated_nodes ) && ( count( $validated_nodes ) > 0 ) )
                {
                    foreach ($validated_nodes as $validated_node)
                    {
                        $validated_node->validator_id = $to_user->id;

                        $validated_node->push();

//                        Notification::where("type", "App\\Notifications\\AskPermission")
//                            ->where("data->node_id", $validated_node->id)
//                            ->update( ["notifiable_id" => $to_user->id] );
                    }

//                    return $validated_node;
                }

                Notification::where("notifiable_id", $from_user->id)
                    ->update( ["notifiable_id" => $to_user->id] );

                $service_ids = [];

                foreach ($from_user->services as $service) $service_ids[] = $service->id;

                foreach ($service_ids as $service_id)
                {
                    $idx = array_search( $service_id, array_map( function ($service){ return $service["id"]; }, $to_user->services->toArray() ) );

                    if ( is_integer($idx) ) continue;

                    $to_user->services()->attach( $service_id );

//                    DB::rollBack();

//                    return "www";
                }

                if ( $from_user->right_lvl > $to_user->right_lvl )
                {
                    $to_user->right_lvl = $from_user->right_lvl;

                    $to_user->push(); $to_user->refresh();
                }

            }

            Notification::where('type', 'App\Notifications\NewUserNotification')
                ->where('data->user_id', $request->from_id)
                ->orWhere('data->user_id', $request->to_id)
                ->delete();

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        DB::commit();

//        AuthUserUpdate::dispatch($from_user);

        return ResponseTrait::get_success("GOOD !!!");

    }


    public function create_service(Request $request)
    {
        DB::beginTransaction();


        try {

            $request->validate([
                'name' => ['required', 'string'],
            ]);

            $service = new Service(['name' => $request->name]);

            $service->push();

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        DB::commit();

//        AuthUserUpdate::dispatch($from_user);

        return ResponseTrait::get_success($service);
    }

    public function delete_service(Request $request)
    {

        DB::beginTransaction();


        try
        {

            $request->validate([
                'id' => ['required', 'integer'],
            ]);

            $service = Service::find( $request->id );

            $serviable_nodes = $service->serviables();
            $serviable_nodes_sauv = json_decode( json_encode( $serviable_nodes ) );
//            return $service;

            foreach ($serviable_nodes as $key => $serviable)
            {
                if ( $this->find_node( $serviable->id, get_class($serviable) ) )
                {
//                    DB::rollBack();
//                    return $serviable->path->value;
//                    $t = new Section();
//                    $t->services()->
//                    $serviable->services;
                    $serviable->services()->detach([$service->id]);
                    $serviable->refresh();

//                    $e = $this->find_node( $serviable->id, get_class($serviable) )->services;
//                    DB::rollBack();
//                    return $serviable->services;

                    if ( count($serviable->services()->get()) ) continue;

                    $serviable->delete();
                }
            };

            $service->refresh();

            foreach ($service->users as $user)
            {
                $user->services()->detach([$service->id]);
                $user->refresh();

                if ( count($user->services()->get()) ) continue;

                $user->delete();
            }

            foreach ($serviable_nodes_sauv as $key => $serviable)
            {
                $serviable_path = $serviable->path[0]->value;
                if ( Storage::exists("public/$serviable_path") )
                {
//                    DB::rollBack();
//                    return $serviable_path;
                    if ( $serviable instanceof Fichier) Storage::delete( "public/$serviable_path" );
                    else Storage::deleteDirectory( "public/$serviable_path" );
                }
            };

            $service->refresh();
            $service->delete();
//            $service = Service::find( $service->id ) ?? "not exist";

//            DB::rollBack();
//            return $service->path->value;

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        DB::commit();

        return ResponseTrait::get_success("GOOD !!");
    }

    public function describe_service(Request $request)
    {
        DB::beginTransaction();


        try {

            $request->validate([
                'id' => ['required', 'integer'],
                'description' => ['required', 'string'],
            ]);

            $service = Service::find($request->id);

            $service->description = $request->description == "" ? "Aucune description apportée" : $request->description;

            $service->push();
            $service->refresh();

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        DB::commit();

        return ResponseTrait::get_success($service);
    }


    public function create_section(Request $request)
    {
        DB::beginTransaction();

        try {

            $request->validate([
                'name' => ['required', 'string'],
            ]);

            $section = new Section(['name' => $request->name]);

            $section->push();

            $section->path()->create(['value' => $request->name]);

            $section->push();
            $section->refresh();

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        DB::commit();

//        AuthUserUpdate::dispatch($from_user);

        return ResponseTrait::get_success($section);
    }

    public function describe_section(Request $request)
    {
        DB::beginTransaction();


        try {

            $request->validate([
                'id' => ['required', 'integer'],
                'description' => ['required', 'string'],
            ]);

            $section = Section::find($request->id);

            $section->description = $request->description == "" ? "Aucune description apportée" : $request->description;

            $section->push();
            $section->refresh();

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        DB::commit();

        return ResponseTrait::get_success($section);
    }

    public function admin_update_section(Request $request)
    {
        DB::beginTransaction();

        $GLOBALS['to_broadcast'] = [];


        try
        {

            $request->validate([
                'id' => ['required', 'integer'],
                'update_object' => ['required', 'string'],
                'new_value' => ['required'],
            ]);

            $section = Section::find($request->id);

            if (!$section) throw new Exception("Section inexistant !!");

            switch ($request->update_object)
            {
                case 'services':
                {
//                    return $request->new_value;
                    $service_names = json_decode($request->new_value);

                    $section->services()->detach();
                    foreach ($service_names as $service_name)
                    {
                        $service_id = Service::where("name", $service_name)->first()->id;

                        $section->services()->attach($service_id);
                    }

                    $section->push();
                    $section->refresh();

                    $this->update_children_service($section);

                    break;
                }
                default:
                    DB::rollBack();

                    return ResponseTrait::get('success', 'Nothing was done');
            }

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        DB::commit();

        $GLOBALS['to_broadcast'] = [];

        return ResponseTrait::get_success($section);

    }

    public function delete_section(Request $request)
    {

        DB::beginTransaction();


        try
        {

            $request->validate([
                'id' => ['required', 'integer'],
            ]);

            $section = Section::find( $request->id );

            $path = $section->path->value;

            $section->delete();

            Storage::deleteDirectory("public/$path");

        }
        catch (\Throwable $th)
        {
            DB::rollBack(); // NO --> some error has occurred undo the whole thing

            return ResponseTrait::get_error($th);
        }

        DB::commit();

        return ResponseTrait::get_success("GOOD !!");
    }

}
