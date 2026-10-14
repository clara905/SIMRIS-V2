<?php

namespace App\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KepatuhanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource instanceof Model ? $this->resource->toArray() : (array) $this->resource;
    }
}
