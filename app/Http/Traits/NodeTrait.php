<?php

namespace App\Http\Traits;

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CheckListController;
use App\Http\Controllers\DossierPreuveController;
use App\Http\Controllers\DossierSimpleController;
use App\Http\Controllers\NcController;
use App\Http\Controllers\NonConformiteController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\UserController;
use App\Notifications\AskPermission;
use Illuminate\Support\Facades\Auth;

trait NodeTrait
{

    public function find_node($id, $type)
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

            default:
                return 'nothing';
        }

    }

    public function can_modify_node($node, $approved = false)
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

    public function can_modify_valid_state($node)
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

    protected function ask_permission_for($operation, $node)
    {
        $validator = UserController::find($node->validator_id);

        $validator->notify(new AskPermission($node, $operation));
    }



}
