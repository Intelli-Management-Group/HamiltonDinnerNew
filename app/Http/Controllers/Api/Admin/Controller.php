<?php

namespace App\Http\Controllers\Api\Admin;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller as BaseController;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;
    var $success_code = 200;

    /**
     * Method to return result json
     * @param Boolean $status = true if success,false if error
     * @param String $msg = Success/Error message
     * @param Array $data = Array containing data to return
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResultJSON($status, $msg = null, $data = array())
    {
        $return_data = array(
            'ResponseCode' => $status,
            'ResponseText' => $msg
        );
        foreach ($data as $key => $value) {
            $return_data[$key] = $value;
        }
        return response()->json($return_data, $this->success_code);
    }
}