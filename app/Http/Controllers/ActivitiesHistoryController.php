<?php

namespace App\Http\Controllers;

use App\Models\Activities_history;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActivitiesHistoryController extends Controller
{
    //


    public static function record_activity($target, string $operation, $services_names = null)
    {
        DB::beginTransaction();

        try
        {
            $user = Auth::user();

            $services_names = [];

            if (!$services_names)
            {
                if ($target->services) foreach ($target->services as $service) array_push($services_names, $service->name);
            }

            $activity = new Activities_history(
                [
                    "id" => Str::uuid(),
                    "user_inspector_number" => $user->inspector_number,
                    "user_name" => "{$user->name} {$user->second_name}",
                    "target_id" => $target->id,
                    "target_type" => get_class($target),
                    "target_name" => get_class($target) == "App\Models\User" ? "{$target->name} {$target->second_name}" : $target->name,
                    "operation" => $operation,
                    "services" => json_encode($services_names)
                ]
            );

            $activity->push();
            $activity->refresh();
        }
        catch (\Throwable $th)
        {
            DB::rollBack();

            throw $th;
        }

        DB::commit();

        return true;
    }


    private static function formatted_type(string $type)
    {
        switch ($type)
        {
            case "App\Models\User":
                return "de l'Utilisateur";
            case "App\Models\Section":
                return "de la Section";
            case "App\Models\Audit":
                return "de l'Audit";
            case "App\Models\checkList":
                return "de la CheckList";
            case "App\Models\DossierPreuve":
                return "du Dossier Preuve";
            case "App\Models\Nc":
                return "du Dossier NC";
            case "App\Models\NonConformite":
                return "de la FNC";
            case "App\Models\DossierSimple":
                return "du Dossier Simple";
            case "App\Models\Fichier":
                return "du Fichier";

            default:
                return 'nothing';
        }
    }
    private static function formatted_operation(string $operation)
    {
        switch ($operation)
        {
            case "add":
                return "Ajout";
            case "delete":
                return "Suppression";
            case "validate":
                return "Validation";
            case "invalidate":
                return "Dévalidation";
            case "close":
                return "Clôture";
            case "set_review":
                return "Programation de révision";
            case "cancel_review":
                return "Réinitialisation de la date de révision";
            case "set_opening":
                return "Mise à jour de la date d'ouverture";
            case "set_level":
                return "Mise à jour du niveau";
            case "rename":
                return "Renommage";
            case "move":
                return "Déplacement";
            case "copy":
                return "Copie";
            default:
                return '"Operation inconnu"';
        }
    }
    private static function get_msg(Activities_history $activity)
    {
        return self::formatted_operation($activity->operation)." ".self::formatted_type($activity->target_type)." {$activity->target_name} par {$activity->user_name}";
    }
    public static function get_history()
    {
        $activities = Activities_history::latest('created_at')->get(); // ->limit(10)

        foreach ($activities as $activity) $activity->msg = self::get_msg($activity);

        return $activities;
    }

}
