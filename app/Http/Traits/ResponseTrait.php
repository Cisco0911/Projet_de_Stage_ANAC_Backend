<?php
namespace App\Http\Traits;








trait ResponseTrait
{

    public static function get($statue, $data)
    {
        if (is_string($data))
        {
            $msg = new \stdClass();

            $msg->msg = $data;
        }

        $res =
            [
                'statue' => $statue,
                'data' => $msg ?? $data,
            ];

        return $res;

    }

//    public function getResponse()
//    {
//
//    }
}
