<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\DossierSimpleController;

class NodeController extends Controller
{
    //

    protected $folderController;

    public function __construct()
    {
        $this->folderController = new DossierSimpleController;
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

                                    return $res;
                                }
                            case 'delete':
                                # code...
                                break;
                            case 'update':
                                # code...
                                break;
                            
                            default:
                                # code...
                                break;
                        }
                        
                        return $res;
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
