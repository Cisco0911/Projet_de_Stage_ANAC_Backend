<?php

namespace App\Http\Traits;

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CheckListController;
use App\Http\Controllers\DossierPreuveController;
use App\Http\Controllers\DossierSimpleController;
use App\Http\Controllers\NcController;
use App\Http\Controllers\NonConformiteController;
use App\Http\Controllers\SectionController;
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

}
