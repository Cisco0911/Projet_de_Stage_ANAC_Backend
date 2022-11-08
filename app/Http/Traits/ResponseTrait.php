<?php
namespace App\Http\Traits;








trait ResponseTrait
{

    public static function get($statue, $data)
    {
        $res =
            [
                'statue' => $statue,
                'data' => $data,
            ];

        return $res;

    }

//    public function getResponse()
//    {
//
//    }
}
