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

    public static function get_info(string $msg)
    {
        $data = new \stdClass();

        $data->msg = $msg;

        $res =
            [
                'statue' => "info",
                'data' => $data,
            ];

        return $res;

    }

    public static function get_error(\Throwable $th)
    {


        $error_object = new \stdClass();

        $error_object->file = $th->getFile();
        $error_object->line = $th->getLine();
        $error_object->msg = $th->getMessage();
        $error_object->code = $th->getCode();

        $res =
            [
                'statue' => "error",
                'data' => $error_object,
            ];

        return $res;

    }

    public static function get_success($data)
    {
        if (is_string($data))
        {
            $msg = new \stdClass();

            $msg->msg = $data;
        }

        $res =
            [
                'statue' => "success",
                'data' => $msg ?? $data,
            ];

        return $res;

    }

//    public function getResponse()
//    {
//
//    }
}
