<?php
namespace App\Http\Traits;

use App\Models\Serviable;








trait ServiableTrait {

    
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


    public function del_from_services($services, $serviable_id, $serviable_type)
    {

        foreach($services as $service)
        {
            if(Serviable::where(
                [
                    'service_id' => $service->value,
                    'serviable_id' => $serviable_id,
                    'serviable_type' => $serviable_type,
                ]
            )->first()->delete()) {}
            else { throw \response('error', 500); }


        }

    }


}