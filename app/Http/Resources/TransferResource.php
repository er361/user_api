<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TransferResource',
    title: 'Transfer Resource',
    description: 'Transfer result containing updated sender and recipient data',
    properties: [
        new OA\Property(
            property: 'sender',
            ref: '#/components/schemas/UserResource',
            description: 'Updated sender information'
        ),
        new OA\Property(
            property: 'recipient',
            ref: '#/components/schemas/UserResource',
            description: 'Updated recipient information'
        )
    ]
)]
class TransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'sender' => new UserResource($this->resource['sender']),
            'recipient' => new UserResource($this->resource['recipient']),
        ];
    }
}
