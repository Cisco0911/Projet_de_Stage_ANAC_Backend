<?php

namespace App\Http\Controllers;

use App\Http\Traits\NodeTrait;
use App\Http\Traits\ResponseTrait;
use App\Http\Traits\ServiableTrait;
use App\Models\Fichier;
use App\Models\Paths;
use App\Notifications\SharingFileNotification;
use Illuminate\Http\Request;

use App\Http\Controllers\AuditController;
use App\Http\Controllers\CheckListController;
use App\Http\Controllers\DossierPreuveController;
use App\Http\Controllers\NcController;
use App\Http\Controllers\NonConformiteController;
use App\Http\Controllers\DossierSimpleController;
use App\Http\Controllers\FichierController;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use SplFileInfo;
use ZipArchive;
use function PHPUnit\Framework\isEmpty;

class NodeController extends Controller
{
    //
    use NodeTrait;
    use ServiableTrait;

    protected \App\Http\Controllers\DossierSimpleController $folderController;
    protected \App\Http\Controllers\FichierController $fileController;
    protected \App\Http\Controllers\AuditController $auditController;
    protected $fncController;

    public function __construct()
    {
        $this->folderController = new DossierSimpleController;
        $this->fileController = new FichierController;

        $this->auditController = new AuditController;
        $this->fncController = new NonConformiteController;
    }


    function find_job(array $jobs, $id)
    {
        foreach ($jobs as $job)
        {
            # code...
            $job = \json_decode($job);

            if( $job->id == $id ) return $job;
        }

        return null;
    }

    function get_dependency_data(array $jobs, $key, $isFnc = false)
    {
        if (!is_int($key))
        {
            $job = $this->find_job($jobs, $key->job_id);

            $res = $job->data->{$key->num};

            $res->state = $job->etat;

            return $res;

        }
        else
        {
            $job = $this->find_job($jobs, $key);

            $job->data->state = $job->etat;
            return $job->data;

//            foreach ($jobs as $job)
//            {
//                # code...
//                $job = \json_decode($job);
//
//                if( $job->id == $key )
//                {
//                    $job->data->state = $job->etat;
//                    return $job->data;
//                }
//            }
        }
    }

    function get_messages($jobs)
    {
        $messages = [];

        foreach ($jobs as $job)
        {
            $message = "";

            switch ($job->node_model)
            {
                case 'App\Models\Audit':
                {
                    switch ($job->operation)
                    {
                        case 'add':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Audit {$job->data->name} ajouté avec succès !";
                            }
                            else
                            {
                                $message = "La création de 'Audit a échoué;\nRaison: {$job->data->msg}";
                            }

                            break;
                        }
                        case 'del':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Audit {$job->data->name} supprimé avec succès !";
                            }
                            else
                            {
                                $message = "La suppression de 'Audit a échoué;\nRaison: {$job->data->msg}";
                            }

                            break;
                        }
                        case 'update':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Audit {$job->data->name} mis à jour avec succès !";
                            }
                            else
                            {
                                $message = "La mise à jour de 'Audit a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }

                        default:
                            # code...
                            break;
                    }

                    break;
                }
                case 'App\Models\NonConformite':
                {
                    switch ($job->operation)
                    {
                        case 'add':
                        {

                            break;
                        }
                        case 'del':
                        {
                            break;
                        }
                        case 'update':
                        {
                            break;
                        }

                        default:
                            # code...
                            break;
                    }

                    break;
                }
                case 'App\Models\DossierSimple':
                {
                    switch ($job->operation)
                    {
                        case 'add':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Dossier {$job->data->name} ajouté avec succès !";
                            }
                            else
                            {
                                $message = "La création de dossier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }
                        case 'del':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Dossier {$job->data->name} supprimé avec succès !";
                            }
                            else
                            {
                                $message = "La suppression de dossier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }
                        case 'update':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Dossier {$job->data->name} mis à jour avec succès !";
                            }
                            else
                            {
                                $message = "La mise à jour de dossier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }
                        case 'add_copy':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Dossier {$job->data->name} copié avec succès !";
                            }
                            else
                            {
                                $message = "La copie de dossier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }
                        case 'move':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Le dossier {$job->data->name} a été déplacé avec succès !";
                            }
                            else
                            {
                                $message = "Le déplacement de dossier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }

                        default:
                            # code...
                            break;
                    }

                    break;
                }
                case 'App\Models\Fichier':
                {
                    switch ($job->operation)
                    {
                        case 'add':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Le(s) fichier(s) a(ont) été ajouté avec succès !";
                            }
                            else
                            {
                                $message = "La création de fichier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }
                        case 'del':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Fichier {$job->data->name} supprimé avec succès !";
                            }
                            else
                            {
                                $message = "La suppression de fichier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }
                        case 'update':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Fichier {$job->data->name} mis à jour avec succès !";
                            }
                            else
                            {
                                $message = "La mise à jour de fichier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }
                        case 'add_copy':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Fichier {$job->data->name} copié avec succès !";
                            }
                            else
                            {
                                $message = "La copie de fichier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }
                        case 'move':
                        {
                            if ($job->etat === "success")
                            {
                                $message = "Le fichier {$job->data->name} a été déplacé avec succès !";
                            }
                            else
                            {
                                $message = "Le déplacement de fichier a échoué;\nRaison: {$job->data->msg}";
                            }
                            break;
                        }

                        default:
                            # code...
                            break;
                    }

                    break;
                }

                default:
                    break;
            }

            array_push($messages, $message);
        }

        return $messages;
    }


    function handle_edit(Request $request)
    {
        if ( Auth::user()->right_lvl < 1 ) throw new Exception("Vous n'avez pas les droits néccésaire !!");

        $request->validate([
            'jobs' => ['required', 'array'],
        ]);

        $jobs = $request->jobs;

//        return ['msg'=>'handle edit early return', 'value' => $request, 'count' => count($request->files)];


        foreach ($jobs as $key => $job)
        {
            # code...
            $job = \json_decode($job);

            switch ($job->node_model)
            {
                case 'App\Models\Audit':
                    # code...
                    {
                        switch ($job->operation)
                        {
                            case 'add':
                            {
                                $res = $this->auditController->add_audit(
                                    new Request(
                                        [
                                            'section_id' => $job->data->section_id,
                                            'name' => $job->data->name,
                                            'ra_id' => $job->data->ra_id,
                                            "inspectors" => $job->data->inspectors,
                                            'services' => \json_encode($job->data->services)

                                        ]
                                    )
                                );


                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

    //                            return $res;

                                break;
                            }
                            case 'del':
                            {
                                $res = $this->auditController->del_audit(
                                    new Request(
                                        [
                                            'id' => $job->node_id,
                                        ]
                                    )
                                );

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

                                break;
                            }
                            case 'update':
                                # code...
                                break;

                            default:
                                # code...
                                break;
                        }

                        // return $res;
                        break;
                    }
                case 'App\Models\checkList':
                    # code...
                    {
                        $audit_job = $this->find_job($jobs, $job->dependencies[0]);

                        $job->etat = $audit_job->etat;

                        if ($job->etat == "success") $job->data = $audit_job->data->check_list;
                        else
                        {
                            $msg = new \stdClass();
                            $msg->msg = $audit_job->data->msg;

                            $job->data = $msg;
                        }

                        $jobs[$key] = json_encode($job);

                        break;
                    }
                case 'App\Models\DossierPreuve':
                    # code...
                    {
                        $audit_job = $this->find_job($jobs, $job->dependencies[0]);

                        $job->etat = $audit_job->etat;

                        if ($job->etat == "success") $job->data = $audit_job->data->dossier_preuve;
                        else
                        {
                            $msg = new \stdClass();
                            $msg->msg = $audit_job->data->msg;

                            $job->data = $msg;
                        }

                        $jobs[$key] = json_encode($job);

                        break;
                    }
                case 'App\Models\Nc':
                    # code...
                    {
                        $audit_job = $this->find_job($jobs, $job->dependencies[0]);

                        $job->etat = $audit_job->etat;

                        if ($job->etat == "success") $job->data = $audit_job->data->nc;
                        else
                        {
                            $msg = new \stdClass();
                            $msg->msg = $audit_job->data->msg;

                            $job->data = $msg;
                        }

                        $jobs[$key] = json_encode($job);

                        break;
                    }
                case 'App\Models\NonConformite':
                    # code...
                    {
                        switch ($job->operation)
                        {
                            case 'add':
                            {
                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if( $dependency_data->state == 'success' )
                                    {
                                        $res = $this->fncController->add_fncs(
                                            new Request(
                                                [
                                                    'nonC_id' => $dependency_data->id,
                                                    'debut' => $job->data->debut,
                                                    'fin' => $job->data->fin,
                                                    'level' => $job->data->level,
                                                    'services' => \json_encode($job->data->services),
                                                    'exceptions' => json_encode($job->exceptions),

                                                ]
                                            )
                                        );

//                                        return $res;
                                    }
                                    else
                                    {
                                        $job->etat = "error";

                                        $msg = new \stdClass();
                                        $msg->msg = $dependency_data->msg;

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                }
                                else
                                {
                                    $res = $this->fncController->add_fncs(
                                        new Request(
                                            [
                                                'nonC_id' => $job->data->nonC_id,
                                                'debut' => $job->data->debut,
                                                'fin' => $job->data->fin,
                                                'level' => $job->data->level,
                                                'services' => \json_encode($job->data->services),
                                                'exceptions' => json_encode($job->exceptions),

                                            ]
                                        )
                                    );
                                }

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

                                // return $res;

                                break;
                            }
                            case 'del':
                            {
                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if( $dependency_data->state == 'success' )
                                    {
                                        $res = $this->fncController->del_fnc(
                                            new Request(
                                                [
                                                    'id' => $dependency_data->id,
                                                ]
                                            )
                                        );
                                    }
                                }
                                else
                                {
                                    $res = $this->fncController->del_fnc(
                                        new Request(
                                            [
                                                'id' => $job->node_id,
                                            ]
                                        )
                                    );
                                }

                                break;
                            }
                            case 'update':
                            {
                                if( !empty($job->dependencies) )
                                {
                                    $fnc_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if( $fnc_data->state == 'success' )
                                    {
                                        $res = $this->fncController->update_fnc(
                                            new Request(
                                                [
                                                    'id' => $fnc_data->id,
                                                    'update_object' => $job->data->update_object,
                                                    'new_value' => $job->data->new_value,
                                                    'additional_info' => $job->data->additional_info,

                                                ]
                                            )
                                        );
                                    }
                                }
                                else
                                {
                                    $res = $this->fncController->update_fnc(
                                        new Request(
                                            [
                                                'id' => $job->data->id,
                                                'update_object' => $job->data->update_object,
                                                'new_value' => $job->data->new_value,
                                                'additional_info' => $job->data->additional_info

                                            ]
                                        )
                                    );
                                }

//                                return $res;

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

                                // return $res;

                                break;
                            }

                            default:
                                # code...
                                break;
                        }

                        // return $res;
                        break;
                    }
                case 'App\Models\DossierSimple':
                    {
                        switch ($job->operation)
                        {
                            case 'add':
                            {
                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if( $dependency_data->state == 'success' )
                                    {
                                        $res = $this->folderController->add_folder(
                                            new Request(
                                                [
                                                    'section_id' => $job->data->section_id,
                                                    'name' => $job->data->name,
                                                    'parent_id' => $dependency_data->id,
                                                    'parent_type' => $job->data->parent_type,
                                                    'services' => \json_encode($job->data->services)

                                                ]
                                            )
                                        );
                                    }
                                    else
                                    {
                                        $job->etat = "error";

                                        $msg = new \stdClass();
                                        $msg->msg = $dependency_data->msg;

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                }
                                else
                                {
                                    $res = $this->folderController->add_folder(
                                        new Request(
                                            [
                                                'section_id' => $job->data->section_id,
                                                'name' => $job->data->name,
                                                'parent_id' => $job->data->parent_id,
                                                'parent_type' => $job->data->parent_type,
                                                'services' => \json_encode($job->data->services)

                                            ]
                                        )
                                    );
                                }

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

//                                 return $res;

                                break;
                            }
                            case 'del':
                            {
                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if( $dependency_data->state == 'success' )
                                    {
                                        $res = $this->folderController->del_folder(
                                            new Request(
                                                [
                                                    'id' => $dependency_data->id,
                                                ]
                                            )
                                        );
                                    }
                                }
                                else
                                {
                                    $res = $this->folderController->del_folder(
                                        new Request(
                                            [
                                                'id' => $job->node_id,
                                            ]
                                        )
                                    );
                                }

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

                                break;
                            }
                            case 'copy':
                            {

                                $destination_id = $job->data->destination_id;
                                $destination_type = $job->data->destination_type;
                                $id = $job->data->id;
                                $on_exist = $job->data->on_exist;

                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if ( empty($dependency_data) || ($dependency_data->state == 'error') )
                                    {
                                        $job->etat = 'error';

                                        $msg = new \stdClass();
                                        $msg->msg = $dependency_data->msg;

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                    elseif ($dependency_data->state == 'success')
                                    {
                                        $destination_id = $dependency_data->id;
                                    }

                                }
                                if ( !empty($job->from_dependency) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->from_dependency[0]);

                                    if ( empty($dependency_data) || ($dependency_data->state == 'error') )
                                    {
                                        $job->etat = 'error';

                                        $msg = new \stdClass();
                                        $msg->msg = $dependency_data->msg;

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                    elseif ($dependency_data->state == 'success')
                                    {
                                        $id = $dependency_data->id;
                                    }

                                }

                                $res = $this->folderController->copy_folder(
                                    new Request(
                                        [
                                            'destination_id' => $destination_id,
                                            'destination_type' => $destination_type,
                                            'id' => $id,
                                            'on_exist' => $on_exist,
                                        ]
                                    )
                                );

//                                return $res;

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

//                                return $jobs;
                                break;
                            }
                            case 'add_copy':
                            {
                                $copy_job = $this->find_job($jobs, $job->copy_job_id);

                                if ($copy_job->etat == "success")
                                {
                                    $new_folder = $copy_job->data->{$job->data->relative_path};

                                    $job->etat = "success";
                                    $job->data = $new_folder;
                                }
                                else
                                {
                                    $job->etat = "error";

                                    $msg = new \stdClass();
                                    $msg->msg = "Erreur de création de copie: {$job->data->relative_path}";

                                    $job->data = $msg;
                                }

                                $jobs[$key] = json_encode($job);

//                                return $jobs;
                                break;
                            }
                            case 'move':
                            {

                                $destination_id = $job->data->destination_id;
                                $destination_type = $job->data->destination_type;
                                $id = $job->data->id;
                                $on_exist = $job->data->on_exist;

                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if ( empty($dependency_data) || ($dependency_data->state == 'error') )
                                    {
                                        $job->etat = 'error';

                                        $msg = new \stdClass();
                                        $msg->msg = $dependency_data->msg;

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                    elseif ($dependency_data->state == 'success')
                                    {
                                        $destination_id = $dependency_data->id;
                                    }
                                }
                                if ( !empty($job->from_dependency) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->from_dependency[0]);

                                    if ( empty($dependency_data) || ($dependency_data->state == 'error') )
                                    {
                                        $job->etat = 'error';

                                        $msg = new \stdClass();
                                        $msg->msg = $dependency_data->msg;

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                    elseif ($dependency_data->state == 'success')
                                    {
                                        $id = $dependency_data->id;
                                    }

                                }

                                $res = $this->folderController->move_folder(
                                    new Request(
                                        [
                                            'destination_id' => $destination_id,
                                            'destination_type' => $destination_type,
                                            'id' => $id,
                                            'on_exist' => $on_exist,
                                        ]
                                    )
                                );

//                                return $res;

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

//                                return $jobs;
                                break;
                            }
                            case 'update':
                                # code...
                                break;

                            default:
                                # code...
                                break;
                        }

                        // return $res;
                        break;
                    }
                case 'App\Models\Fichier':
                    # code...
                    {
                        switch ($job->operation)
                        {
                            case 'add':
                            {
                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if( $dependency_data->state == 'success' )
                                    {
                                        $res = $this->fileController->add_files(
                                            new Request(
                                                [
                                                    'section_id' => $job->data->section_id,
                                                    'fichiers' => $request['job'.$job->id.'_files'],
                                                    'parent_id' => $dependency_data->id,
                                                    'parent_type' => $job->data->parent_type,
                                                    'services' => \json_encode($job->data->services)

                                                ]
                                            )
                                        );
                                    }
                                    else
                                    {
                                        $job->etat = "error";

                                        $msg = new \stdClass();
                                        $msg->msg = $dependency_data->msg;

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                }
                                else
                                {
                                    $res = $this->fileController->add_files(
                                        new Request(
                                            [
                                                'section_id' => $job->data->section_id,
                                                'fichiers' => $request['job'.$job->id.'_files'],
                                                'parent_id' => $job->data->parent_id,
                                                'parent_type' => $job->data->parent_type,
                                                'services' => \json_encode($job->data->services)

                                            ]
                                        )
                                    );
                                }

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

//                                 return $res;

                                break;
                            }
                            case 'del':
                            {
                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if( $dependency_data->state == 'success' )
                                    {
                                        $res = $this->fileController->del_file(
                                            new Request(
                                                [
                                                    'id' => $dependency_data->id,
                                                ]
                                            )
                                        );
                                    }
                                }
                                else
                                {
                                    $res = $this->fileController->del_file(
                                        new Request(
                                            [
                                                'id' => $job->node_id,
                                            ]
                                        )
                                    );
                                }

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

                                break;
                            }
                            case 'copy':
                            {

                                $destination_id = $job->data->destination_id;
                                $destination_type = $job->data->destination_type;
                                $id = $job->data->id;
                                $on_exist = $job->data->on_exist;

                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);
//                                    return $dependency_data;

                                    if ( empty($dependency_data) || ($dependency_data->state == 'error') )
                                    {
                                        $job->etat = 'error';

                                        $msg = new \stdClass();
                                        $msg->msg = "dependency n'a pas marché";

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                    elseif ($dependency_data->state == 'success')
                                    {
                                        $destination_id = $dependency_data->id;
                                    }

                                }
                                if ( !empty($job->from_dependency) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->from_dependency[0]);

                                    if ( empty($dependency_data) || ($dependency_data->state == 'error') )
                                    {
                                        $job->etat = 'error';

                                        $msg = new \stdClass();
                                        $msg->msg = "from_dependency n'a pas marché";

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                    elseif ($dependency_data->state == 'success')
                                    {
                                        $id = $dependency_data->id;
                                    }

                                }

                                $res = $this->fileController->copy_file(
                                    new Request(
                                        [
                                            'destination_id' => $destination_id,
                                            'destination_type' => $destination_type,
                                            'id' => $id,
                                            'on_exist' => $on_exist,
                                        ]
                                    )
                                );

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

//                                return $jobs;
                                break;
                            }
                            case 'add_copy':
                            {
                                $copy_job = $this->find_job($jobs, $job->copy_job_id);

                                if ($copy_job->etat == "success")
                                {
                                    if ($copy_job->node_model == "App\Models\Fichier")
                                    {
                                        $new_file = $copy_job->data;
                                    }
                                    else
                                    {
                                        $new_file = $copy_job->data->{$job->data->relative_path};
                                    }

                                    $job->etat = "success";
                                    $job->data = $new_file;
                                }
                                else
                                {
                                    $job->etat = "error";

                                    $msg = new \stdClass();
                                    $msg->msg = "Erreur de création de copie: {$job->data->relative_path}";

                                    $job->data = $msg;
                                }

                                $jobs[$key] = json_encode($job);

//                                return $jobs;
                                break;
                            }
                            case 'move':
                            {

                                $destination_id = $job->data->destination_id;
                                $destination_type = $job->data->destination_type;
                                $id = $job->data->id;
                                $on_exist = $job->data->on_exist;

                                if( !empty($job->dependencies) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->dependencies[0]);

                                    if ( empty($dependency_data) || ($dependency_data->state == 'error') )
                                    {
                                        $job->etat = 'error';

                                        $msg = new \stdClass();
                                        $msg->msg = "dependency n'a pas marché";

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                    elseif ($dependency_data->state == 'success')
                                    {
                                        $destination_id = $dependency_data->id;
                                    }
//                                    return [$dependency_data, $job];

                                }
                                if ( !empty($job->from_dependency) )
                                {
                                    $dependency_data = $this->get_dependency_data($jobs, $job->from_dependency[0]);

                                    if ( empty($dependency_data) || ($dependency_data->state == 'error') )
                                    {
                                        $job->etat = 'error';

                                        $msg = new \stdClass();
                                        $msg->msg = "from_dependency n'a pas marché";

                                        $job->data = $msg;

                                        $jobs[$key] = json_encode($job);

                                        break;
                                    }
                                    elseif ($dependency_data->state == 'success')
                                    {
                                        $id = $dependency_data->id;
                                    }

                                }

                                $res = $this->fileController->move_file(
                                    new Request(
                                        [
                                            'destination_id' => $destination_id,
                                            'destination_type' => $destination_type,
                                            'id' => $id,
                                            'on_exist' => $on_exist,
                                        ]
                                    )
                                );

//                                return $res;

                                $job->etat = $res["statue"];
                                $job->data = $res["data"];

                                $jobs[$key] = json_encode($job);

//                                return $jobs;
                                break;
                            }
                            case 'update':
                                # code...
                                break;

                            default:
                                # code...
                                break;
                        }

                        // return $res;
                        break;
                    }

                default:
                    # code...
                    return "default";
                    break;
            }

        }

        $lol = array_map(function( $job_element) { return json_decode($job_element);}, $jobs);

//        $msg = $this->get_messages( $lol );

        return $lol;

    }


    protected function addContent(\ZipArchive $zip, string $root_path)
    {

        $root_name = basename($root_path);
        $zip->addEmptyDir($root_name);

        /** @var SplFileInfo[] $nodes */
        $nodes = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $root_path,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

//        $lol = [];

        foreach ($nodes as $node)
        {
            $filePath = $node->getPathName();
            $relativePath = "$root_name\\".substr($filePath, strlen($root_path) + 1);

//            return $relativePath;

//            $lal = new \stdClass();
//
//            $lal->filePath = $node->getPathName();
//            $lal->relativePath = substr($filePath, strlen($root_path) + 1);
//
//            array_push($lol, $lal);

                if ($node->isFile()) $zip->addFile($filePath, $relativePath);
                elseif ($node->isDir())
                {
//                    return "DIR";
                    if ($relativePath !== false)
                    {
                        $zip->addEmptyDir($relativePath);
                    }
                }
        }

        return "Fin";
    }

    public function compress(Request $request)
    {

        $request->validate([
            'nodes_info' => ['required', 'json'],
        ]);

        $nodes_info = json_decode($request->nodes_info);
//        return $nodes_info;

        $zip = new ZipArchive;

        $user_cache_folder = Auth::user()->name.Auth::id();

        if (!Storage::exists("cache\\$user_cache_folder")) Storage::makeDirectory("cache\\$user_cache_folder");

        $zip_path = storage_path("app\\cache\\$user_cache_folder\\ARCHIVE.zip");

        if ($zip->open($zip_path, ZipArchive::CREATE) === TRUE)
        {
//            $files = File::files(public_path('myFiles'));

            foreach ($nodes_info as $key => $info) {

                $node = $this->find_node($info->id, $info->model);

//                return storage_path("app\\public\\{$node->path->value}");
//                $zip->addFile(storage_path("C:\laragon\www\Bibliotheque-technique--ANAC\storage\Nouveau Dossier"), $relativeNameInZipFile);

                if ($node instanceof Fichier)
                {
                    $zip->addFile(storage_path("app\\public\\{$node->path->value}"), $node->name);
                }
                else
                {
//                    return $node;
                    $res = $this->addContent($zip, storage_path("app\\public\\{$node->path->value}"));
                }
            }

            $zip->close();
        }
        else
        {
            return "ERROR";
        }

        return $zip_path;
    }

    public function download_by_path(Request $request)
    {
        return response()->download($request->path)->deleteFileAfterSend();
    }

    public function share(Request $request)
    {

        try {

            $request->validate([
                'nodes_info' => ['required', 'json'],
                'inspectors' => ['required', 'json'],
            ]);

            $nodes_info = json_decode($request->nodes_info);
            $inspector_ids = json_decode($request->inspectors);

            if ( (count($nodes_info) == 1) && ($nodes_info[0]->model == "App\\Models\\Fichier") )
            {
                $file = FichierController::find( $nodes_info[0]->id );

                foreach ($inspector_ids as $inspector_id)
                {
                    $user = UserController::find($inspector_id);

                    $user->notify( new SharingFileNotification( storage_path("app\\public\\{$file->path->value}") ) );
                }

                return ResponseTrait::get_success(["one file", $nodes_info, $inspector_ids]);
            }
            else
            {
                $path = $this->compress(
                    new Request(
                        [
                            'nodes_info' => json_encode($nodes_info),
                        ]
                    )
                );

                foreach ($inspector_ids as $inspector_id)
                {
                    $user = UserController::find($inspector_id);

                    $user->notify( new SharingFileNotification( $path ) );
                }

                unlink($path);

                return ResponseTrait::get_success([$nodes_info, $inspector_ids]);
            }
        }
        catch (\Throwable $th)
        {
            return ResponseTrait::get_error($th);
        }

    }


    public function detach_from_service(Request $request)
    {


        DB::beginTransaction();

        try {
            //code...

            $request->validate([
                'node_id' => ['required', 'integer'],
                'node_type' => ['required', 'string', 'max:255'],
                'services_ids' => ['required', 'array'],
            ]);

//            if (!(int)Auth::id())  throw new \Exception("Vous etes pas Administrateur !");

            $node = $this->find_node($request->node_id, $request->node_type);

            $this->del_from_services($request->services_ids, $node);

        }
        catch (\Throwable $th)
        {
            DB::rollBack();

            return ResponseTrait::get_error($th);
        }

        DB::commit();

        return ResponseTrait::get_success("GOOD");

    }


}
