<?php

namespace App\Http\Traits;

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CheckListController;
use App\Http\Controllers\DossierPreuveController;
use App\Http\Controllers\DossierSimpleController;
use App\Http\Controllers\FichierController;
use App\Http\Controllers\NcController;
use App\Http\Controllers\NonConformiteController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\UserController;
use App\Models\Audit;
use App\Models\DossierSimple;
use App\Models\Fichier;
use App\Models\Nc;
use App\Models\NonConformite;
use App\Models\Notification;
use App\Models\Section;
use App\Notifications\AskPermission;
use Illuminate\Support\Facades\Auth;

trait NodeTrait
{
    protected function global_format($nodes, $model)
    {
        $nodes_array = [];

        foreach ($nodes as $node) array_push($nodes_array, $node);

        $nodes = $nodes_array;

        switch ($model) {
            case "App\Models\Section":
                return $nodes;
            case "App\Models\Audit":
                $format = function ($audit){ return AuditController::format($audit); };
                return array_map($format, $nodes);
            case "App\Models\checkList":
                $format = function ($checkList){ return CheckListController::format($checkList); };
                return array_map($format, $nodes);
            case "App\Models\DossierPreuve":
                $format = function ($dp){ return DossierPreuveController::format($dp); };
                return array_map($format, $nodes);
            case "App\Models\Nc":
                $format = function ($nonC){ return NcController::format($nonC); };
                return array_map($format, $nodes);
            case "App\Models\NonConformite":
                $format = function ($fnc){ return NonConformiteController::format($fnc); };
                return array_map($format, $nodes);
            case "App\Models\DossierSimple":
                $format = function ($dossier){ return DossierSimpleController::format($dossier); };
                return array_map($format, $nodes);
            case "App\Models\Fichier":
                $format = function ($fichier){ return FichierController::format($fichier); };
                return array_map($format, $nodes);

            default:
                return $nodes;
        }
    }

    protected function find_node($id, $type)
    {

        switch ($type) {
            case "App\Models\Section":
                return SectionController::find((int)$id);
            case "App\Models\Audit":
                return AuditController::find((int)$id);
            case "App\Models\checkList":
                return CheckListController::find((int)$id);
            case "App\Models\DossierPreuve":
                return DossierPreuveController::find((int)$id);
            case "App\Models\Nc":
                return NcController::find((int)$id);
            case "App\Models\NonConformite":
                return NonConformiteController::find((int)$id);
            case "App\Models\DossierSimple":
                return DossierSimpleController::find((int)$id);
            case "App\Models\Fichier":
                return FichierController::find((int)$id);

            default:
                return 'nothing';
        }

    }

    protected function get_children($node)
    {
        $children = [];

        if ($node instanceof Section)
        {
            foreach ($node->audits()->get() as $audit)
            {
                array_push($children, $audit);
            }
        }
        elseif ($node instanceof Audit)
        {
            array_push($children, $node->checklist()->first(), $node->dossier_preuve()->first(), $node->nc()->first());
        }
        elseif ($node instanceof Nc)
        {
            foreach ($node->fncs()->get() as $fnc)
            {
                array_push($children, $fnc);
            }
        }


        foreach ($node->dossiers()->get() as $dossier)
        {
            array_push($children, $dossier);
        }
        foreach ($node->fichiers()->get() as $fichier)
        {
            array_push($children, $fichier);
        }

        return $children;
    }

    protected function get_broadcast_id($node)
    {
        switch ( get_class($node) )
        {
            case "App\Models\Audit":
                $type = 'audit';
                break;
            case "App\Models\checkList":
                $type = 'checkList';
                break;
            case "App\Models\DossierPreuve":
                $type = 'dp';
                break;
            case "App\Models\Nc":
                $type = 'nonC';
                break;
            case "App\Models\NonConformite":
                $type = 'fnc';
                break;
            case "App\Models\DossierSimple":
                $type = 'ds';
                break;
            case "App\Models\Fichier":
                $type = 'f';
                break;

            default:
                $type = '';
                break;
        }

        return $node->id."-$type";
    }

    protected function get_top_lvl_parent($node)
    {
        if ($node instanceof Audit) return $node;
        if ( !empty($node->parent_type) && $node->parent_type == "App\\Models\\Section" ) return $node;

        if ( ($node instanceof DossierSimple) || ($node instanceof Fichier) ) return $this->get_top_lvl_parent($node->parent);
        else if ($node instanceof NonConformite) return $node->nc_folder->audit;
        else return $node->audit;
    }

    protected function can_modify_node($node, $approved = false)
    {
        if ($approved) return 2;

        $node_services_ids = array_map( function ($service){ return $service["id"]; }, $node->services->toArray() );
        $user_services_ids = array_map( function ($service){ return $service["id"]; }, Auth::user()->services->toArray() );

        $intersect = array_intersect( $node_services_ids, $user_services_ids );

        if ( !count($intersect) ) return 0;

        if ($node->is_validated)
        {
            if ( ((int)Auth::user()->right_lvl == 2) && ($node->validator_id == Auth::id()) ) return 2;
            elseif ( (int)Auth::user()->right_lvl > 0 ) return 1;
        }
        elseif ( (int)Auth::user()->right_lvl > 0 ) return 2;

        return 0;
    }

    protected function can_modify_valid_state($node)
    {
        if ( $node->is_validated)
        {
            if (Auth::id() == $node->validator_id) return true;
            return false;
        }

        $top_lvl_parent = $this->get_top_lvl_parent($node);
        if ($top_lvl_parent instanceof Audit)
        {
            if ($top_lvl_parent->user->id == Auth::id()) return true;
        }

        if (!$node->is_validated)
        {
            if ( (int)Auth::user()->right_lvl == 2 )
            {
                foreach ($node->services as $service)
                {
                    foreach (Auth::user()->services as $user_service)
                    {
                        if ($user_service->id == $service->id)
                        {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    protected function valid_node($node)
    {
        if (!$node->is_validated)
        {
            $node->is_validated = 1;
            $node->validator_id = Auth::id();
            $node->push();

            $node->refresh();

            array_push($GLOBALS['to_broadcast'], $node);
        }
        else
        {
            if ( Auth::id() != $node->validator_id ) throw new \Exception("l'élément est validé ou contient un élément validé par autre que vous !!");
        }

        if (!empty($node->dossiers))
        {
            foreach ($node->dossiers as $dossier) $this->valid_node($dossier);
        }

        if (!empty($node->fichiers))
        {
            foreach ($node->fichiers as $fichier) $this->valid_node($fichier);
        }

        if (!empty($node->checklist))
        {
            $this->valid_node($node->checklist);
        }

        if (!empty($node->dossier_preuve))
        {
            $this->valid_node($node->dossier_preuve);
        }

        if (!empty($node->nc))
        {
            $this->valid_node($node->nc);
        }

        if (!empty($node->fncs))
        {
            foreach ($node->fncs as $fnc) $this->valid_node($fnc);
        }

        return $node;
    }

    protected function unvalid_node($node)
    {
        if ($node->is_validated)
        {
            $node->is_validated = 0;
            $node->validator_id = null;
            $node->push();

            $node->refresh();

            Notification::where("type", "App\\Notifications\\AskPermission")
                ->where("data->node_id", $node->id)
                ->delete();

            array_push($GLOBALS['to_broadcast'], $node);
        }

        if (!empty($node->parent))
        {
            $this->unvalid_node($node->parent);
        }

        if (!empty($node->audit))
        {
            $this->unvalid_node($node->audit);
        }

        if (!empty($node->nc_folder))
        {
            $this->unvalid_node($node->nc_folder);
        }

        return $node;
    }

    protected function can_modify_node_deep_check($node)
    {
        if ( !($this->can_modify_node($node) == 2) ) return false;
        else
        {
            if ( !($node instanceof Fichier) )
            {
                foreach ( $node->dossiers as $dossier )
                {
                    if ( !($this->can_modify_node_deep_check($dossier) == 2) ) return false;
                }
                foreach ( $node->fichiers as $fichier )
                {
                    if ( !($this->can_modify_node_deep_check($fichier) == 2) ) return false;
                }
            }
        }

        return true;
    }

    public function demand_exist($operation, $node)
    {
        switch ($operation)
        {
            case 'modification':
            {
                if ($node->is_validated)
                {
                    $validator = UserController::find($node->validator_id);

                    $modification_notif = $validator->notifications()
                        ->where('type', 'App\Notifications\AskPermission')
                        ->where('data->operation', 'modification')
                        ->where('data->node_id', $node->id)
                        ->first();

                    return $modification_notif;
                }
                break;
            }
            case 'deletion':
            {
                if ($node->is_validated)
                {
                    $validator = UserController::find($node->validator_id);

                    $deletion_notif = $validator->notifications()
                        ->where('type', 'App\Notifications\AskPermission')
                        ->where('data->operation', 'deletion')
                        ->where('data->node_id', $node->id)
                        ->first();

                    return $deletion_notif;
                }
                break;
            }
        }

        return null;
    }

    protected function ask_permission_for($operation, $node)
    {
        if ( $this->demand_exist($operation, $node) ) return false;

        $validator = UserController::find($node->validator_id);

        $validator->notify(new AskPermission($node, $operation));

        return true;
    }

    protected function update_children_service($node)
    {
        $parent_services_ids = [];

        foreach ($node->services()->get() as $service)
        {
            array_push($parent_services_ids, $service->id);
        }

        if ( $node instanceof Fichier ) return;

        foreach ($this->get_children($node) as $child)
        {
            $child_services_ids = [];

            foreach ($child->services()->get() as $service)
            {
                array_push($child_services_ids, $service->id);
            }

            $services_intersection = array_intersect($parent_services_ids, $child_services_ids);
            if ( count($child->services()->get()) > count($services_intersection) )
            {
                $child->services()->detach();
                foreach ( count($services_intersection) ? $services_intersection : $parent_services_ids as $service_id)
                {
                    $child->services()->attach($service_id);
                }

                $this->update_children_service($child);
            }
        }
    }



}
