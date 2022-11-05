<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Http\Controllers\AuditController;
use App\Http\Controllers\DossierSimpleController;
use App\Http\Controllers\FichierController;

use App\Http\Controllers\Controller;
use function PHPUnit\Framework\isEmpty;

class NodeController extends Controller
{
    //

    protected \App\Http\Controllers\DossierSimpleController $folderController;
    protected \App\Http\Controllers\FichierController $fileController;
    protected \App\Http\Controllers\AuditController $auditController;

    public function __construct()
    {
        $this->folderController = new DossierSimpleController;
        $this->fileController = new FichierController;

        $this->auditController = new AuditController;
    }



    function find(array $jobs, $id)
    {
        foreach ($jobs as $key => $job)
        {
            # code...
            $job = \json_decode($job);

            if( $job->id == $id ) return $job;
        }
    }


    function handle_edit(Request $request)
    {
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
                                        'services' => \json_encode($job->data->services)

                                    ]
                                )
                            );


                            if( $res->id )
                            {
                                $job->etat = 'success';
                                $job->data = $res;

                                $jobs[$key] = json_encode($job);

//                                 return $jobs;
                            }
                            else
                            {
                                $job->etat = 'error';

                                $jobs[$key] = json_encode($job);
                            }

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

                            //                                return $res;

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
                    $audit_job = $this->find($jobs, $job->dependencies[0]);

                    if($audit_job->etat == 'success')
                    {
                        $job->etat = 'success';
                        $job->data = $audit_job->data->check_list;

                        $jobs[$key] = json_encode($job);

//                        return $job;
                    }
                    else
                    {

                    }

                    break;
                }
                case 'App\Models\DossierPreuve':
                # code...
                {
                    $audit_job = $this->find($jobs, $job->dependencies[0]);

                    if($audit_job->etat == 'success')
                    {
                        $job->etat = 'success';
                        $job->data = $audit_job->data->dossier_preuve;

                        $jobs[$key] = json_encode($job);
                    }
                    else
                    {

                    }

                    break;
                }
                case 'App\Models\Nc':
                # code...
                {
                    $audit_job = $this->find($jobs, $job->dependencies[0]);

                    if($audit_job->etat == 'success')
                    {
                        $job->etat = 'success';
                        $job->data = $audit_job->data->nc;

                        $jobs[$key] = json_encode($job);
                    }
                    else
                    {

                    }

                    break;
                }
                case 'App\Models\NonConformite':
                    # code...
                    break;
                case 'App\Models\DossierSimple':
                    # code...
                    {
                        switch ($job->operation)
                        {
                            case 'add':
                            {
                                if( !empty($job->dependencies) )
                                {
                                    $dependence = $this->find($jobs, $job->dependencies[0]);

                                    if( $dependence->etat == 'success' )
                                    {
                                        $res = $this->folderController->add_folder(
                                            new Request(
                                                [
                                                    'section_id' => $job->data->section_id,
                                                    'name' => $job->data->name,
                                                    'parent_id' => $dependence->data->id,
                                                    'parent_type' => $job->data->parent_type,
                                                    'services' => \json_encode($job->data->services)

                                                ]
                                            )
                                        );
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

                                if( $res->id )
                                {
                                    $job->etat = 'success';
                                    $job->data = $res;

                                    $jobs[$key] = json_encode($job);

                                    // return $jobs;
                                }
                                else
                                {
                                    $job->etat = 'error';

                                    $jobs[$key] = json_encode($job);
                                }

                                // return $res;

                                break;
                            }
                            case 'del':
                            {
                                $res = $this->folderController->del_folder(
                                    new Request(
                                        [
                                            'id' => $job->node_id,
                                        ]
                                    )
                                );

//                                return $res;

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
                                    $dependence = $this->find($jobs, $job->dependencies[0]);

                                    if( $dependence->etat == 'success' )
                                    {
                                        $res = $this->fileController->add_files(
                                            new Request(
                                                [
                                                    'section_id' => $job->data->section_id,
                                                    'fichiers' => $request['job'.$job->id.'_files'],
                                                    'parent_id' => $dependence->data->id,
                                                    'parent_type' => $job->data->parent_type,
                                                    'services' => \json_encode($job->data->services)

                                                ]
                                            )
                                        );
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

//                                if( $res->id )
//                                {
//                                    $job->etat = 'success';
//                                    $job->data = $res;
//
//                                    $jobs[$key] = json_encode($job);
//
//                                    // return $jobs;
//                                }
//                                else
//                                {
//                                    $job->etat = 'error';
//
//                                    $jobs[$key] = json_encode($job);
//                                }

//                                 return $res;

                                break;
                            }
                            case 'del':
                            {
                                $res = $this->fileController->del_file(
                                    new Request(
                                        [
                                            'id' => $job->node_id,
                                        ]
                                    )
                                );

    //                                return $res;

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

    }


}
