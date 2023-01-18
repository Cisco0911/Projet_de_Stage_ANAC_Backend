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
use App\Models\NonConformite;
use App\Notifications\AskPermission;
use Illuminate\Support\Facades\Auth;

trait NodeTrait
{

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



}
