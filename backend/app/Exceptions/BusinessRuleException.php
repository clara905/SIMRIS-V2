<?php

namespace App\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;

class BusinessRuleException extends HttpResponseException
{
    public function __construct(string $code, string $message, array $problems = [])
    {
        $response = [
            'message' => $message,
            'code' => $code,
        ];

        if ($problems !== []) {
            $response['masalah'] = $problems;
        }

        parent::__construct(response()->json($response, 409));
    }
}
