<?php

namespace App\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;

class BusinessRuleException extends HttpResponseException
{
    public function __construct(string $code, string $message, array $problems = [])
    {
        parent::__construct(response()->json([
            'message' => 'Permintaan tidak dapat diproses. Silakan coba lagi.',
        ], 409));
    }
}
