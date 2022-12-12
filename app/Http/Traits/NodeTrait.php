<?php

namespace App\Http\Traits;

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CheckListController;
use App\Http\Controllers\DossierPreuveController;
use App\Http\Controllers\DossierSimpleController;
use App\Http\Controllers\NcController;
use App\Http\Controllers\NonConformiteController;
use App\Http\Controllers\SectionController;

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

}
