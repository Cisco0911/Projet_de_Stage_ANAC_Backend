<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\DossierSimpleController;
use App\Http\Controllers\Controller;

class NodeController extends Controller
{
    //

    protected \App\Http\Controllers\DossierSimpleController $folderController;

    public function __construct()
    {
        $this->folderController = new DossierSimpleController;
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


        foreach ($jobs as $key => $job)
        {
            # code...
            $job = \json_decode($job);

            switch ($job->node_model)
            {
                case 'App\Models\Audit':
                    # code...
                    break;
                case 'App\Models\checkList':
                    # code...
                    break;
                case 'App\Models\DossierPreuve':
                    # code...
                    break;
                case 'App\Models\Nc':
                    # code...
                    break;
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
                                if( \count($job->dependencies) > 0 )
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

                                return $res;
                            }
                            case 'update':
                                # code...
                                break;

                            default:
                                # code...
                                break;
                        }

                        // return $res;
                    }
                case 'App\Models\Fichier':
                    # code...
                    break;

                default:
                    # code...
                    return "default";
                    break;
            }

        }

    }


}
