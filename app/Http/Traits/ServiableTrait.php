<?php
namespace App\Http\Traits;

use App\Models\Serviable;








trait ServiableTrait
{


    public function add_to_services($services, $serviable_id, $serviable_type) {

        foreach($services as $service)
        {
            Serviable::create(
                [
                    'service_id' => $service->value,
                    'serviable_id' => $serviable_id,
                    'serviable_type' => $serviable_type,
                ]
            );
        }

    }


    public function del_from_services($services_ids, $node)
    {

//        $parse_int = function($id) { return (int)$id; };

        $ids = array_map( function($id) { return (int)$id; }, $services_ids);

        $node->services()->detach($ids);

    }


}
