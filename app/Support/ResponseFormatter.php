<?php

namespace App\Support;

class ApiResponse
{
    /**
     * Format API response for use in Dining App and Forms App.
     */
    public static function format(
        string $status,
        ?string $message = null,
        array $data = [],
        int $statusCode = 200
    ): array {
        $return_data = [
            'ResponseCode' => $status,
            'ResponseText' => $message,
        ];

        $return_data = array_merge($return_data, $data);

        return response()->json(
            $return_data,
            $statusCode
        );
    }
}