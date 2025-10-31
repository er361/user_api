<?php

namespace App\Http\Controllers;

use App\Actions\User\InitiateTransferAction;
use App\Actions\User\TransferBalanceAction;
use App\Actions\User\UpdateUserAction;
use App\Actions\User\UpdateUserBalanceAction;
use App\Http\Requests\ConfirmTransferRequest;
use App\Http\Requests\InitiateTransferRequest;
use App\Http\Requests\UpdateUserBalanceRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Http\Resources\TransferResource;
use App\Http\Resources\UserResource;
use App\Models\TransferConfirmation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

#[OA\Info(
    version: '1.0.0',
    title: 'User API',
    description: 'API for managing users and balance transfers with two-factor confirmation'
)]
#[OA\Server(
    url: 'http://localhost:8000/api',
    description: 'Local development server'
)]
#[OA\SecurityScheme(
    securityScheme: 'bearerAuth',
    type: 'http',
    scheme: 'bearer',
    bearerFormat: 'JWT'
)]
class UserController extends Controller
{
    #[OA\Put(
        path: '/user',
        summary: 'Update authenticated user profile',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com')
                ]
            )
        ),
        tags: ['User Management'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/UserResource')
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function update(
        UpdateUserRequest $request,
        UpdateUserAction $action
    ): JsonResponse {
        $user = $request->user();

        $updatedUser = $action->execute($user, $request->validated());

        return response()->json(new UserResource($updatedUser));
    }

    #[OA\Put(
        path: '/users/{id}/balance',
        summary: 'Update user balance (Admin only)',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'balance',
                        type: 'string',
                        description: 'New balance value (decimal string)',
                        example: '1000.50'
                    )
                ]
            )
        ),
        tags: ['Admin - Balance Management'],
        parameters: [
            new OA\Parameter(
                name: 'id',
                in: 'path',
                required: true,
                description: 'User ID',
                schema: new OA\Schema(type: 'string', example: '1')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Balance updated successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/UserResource')
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 403, description: 'Forbidden - Admin access required'),
            new OA\Response(response: 422, description: 'Validation error')
        ]
    )]
    public function updateBalance(
        UpdateUserBalanceRequest $request,
        string $id,
        UpdateUserBalanceAction $action
    ): JsonResponse {
        $currentUser = $request->user();

        $this->authorize('updateAnyBalance', User::class);

        $user = User::find($id);

        if (!$user) {
            // Return same response whether user exists or not
            usleep(rand(100000, 300000)); // Random delay 100-300ms
            return response()->json([
                'message' => 'This action is unauthorized.',
            ], 403);
        }

        $updatedUser = $action->execute($user, $request->validated()['balance']);

        return response()->json(new UserResource($updatedUser));
    }

    #[OA\Post(
        path: '/transfer/initiate',
        summary: 'Initiate a balance transfer (Step 1)',
        description: 'Initiates a balance transfer between users. Returns a confirmation token that must be used within 5 minutes to complete the transfer.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['recipient_id', 'amount'],
                properties: [
                    new OA\Property(
                        property: 'recipient_id',
                        type: 'integer',
                        description: 'ID of the recipient user',
                        example: 2
                    ),
                    new OA\Property(
                        property: 'amount',
                        type: 'string',
                        description: 'Amount to transfer (decimal string)',
                        example: '100.00'
                    )
                ]
            )
        ),
        tags: ['Balance Transfer'],
        responses: [
            new OA\Response(
                response: 201,
                description: 'Transfer initiated successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Transfer initiated. Please confirm using the provided token.'),
                        new OA\Property(property: 'confirmation_token', type: 'string', example: 'abc123def456'),
                        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', example: '2025-11-01T10:15:00Z'),
                        new OA\Property(property: 'amount', type: 'string', example: '100.00'),
                        new OA\Property(property: 'recipient_id', type: 'integer', example: 2)
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Recipient not found'),
            new OA\Response(response: 422, description: 'Validation error - insufficient balance or invalid amount'),
            new OA\Response(response: 429, description: 'Too many requests - rate limit exceeded')
        ]
    )]
    public function initiateTransfer(
        InitiateTransferRequest $request,
        InitiateTransferAction $action
    ): JsonResponse {
        $sender = $request->user();

        $recipient = User::find($request->validated()['recipient_id']);

        if (!$recipient) {
            usleep(rand(100000, 300000)); // Random delay 100-300ms
            return response()->json([
                'message' => 'Recipient not found.',
            ], 404);
        }

        $confirmation = $action->execute(
            $sender,
            $recipient,
            $request->validated()['amount']
        );

        return response()->json([
            'message' => 'Transfer initiated. Please confirm using the provided token.',
            'confirmation_token' => $confirmation->confirmation_token,
            'expires_at' => $confirmation->expires_at,
            'amount' => $confirmation->amount,
            'recipient_id' => $confirmation->recipient_id,
        ], 201);
    }

    #[OA\Post(
        path: '/transfer/confirm',
        summary: 'Confirm and execute a balance transfer (Step 2)',
        description: 'Confirms and executes a previously initiated transfer using the confirmation token. The token expires after 5 minutes and can only be used once.',
        security: [['bearerAuth' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['confirmation_token'],
                properties: [
                    new OA\Property(
                        property: 'confirmation_token',
                        type: 'string',
                        description: 'Confirmation token received from /transfer/initiate',
                        example: 'abc123def456'
                    )
                ]
            )
        ),
        tags: ['Balance Transfer'],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Transfer completed successfully',
                content: new OA\JsonContent(ref: '#/components/schemas/TransferResource')
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid confirmation - token expired, already used, or blocked',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'This confirmation token has expired.'
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized'),
            new OA\Response(response: 404, description: 'Invalid confirmation token or recipient not found'),
            new OA\Response(response: 429, description: 'Too many requests - rate limit exceeded')
        ]
    )]
    public function confirmTransfer(
        ConfirmTransferRequest $request,
        TransferBalanceAction $action
    ): JsonResponse {
        $sender = $request->user();
        $token = $request->validated()['confirmation_token'];

        $confirmation = TransferConfirmation::where('confirmation_token', $token)
            ->where('user_id', $sender->id)
            ->first();

        if (!$confirmation) {
            usleep(rand(100000, 300000));
            return response()->json([
                'message' => 'Invalid confirmation token.',
            ], 404);
        }

        if (!$confirmation->isValid()) {
            $message = 'Invalid confirmation.';

            if ($confirmation->confirmed) {
                $message = 'This transfer has already been confirmed.';
            } elseif ($confirmation->isExpired()) {
                $message = 'This confirmation token has expired.';
            } elseif ($confirmation->isBlocked()) {
                $message = 'This confirmation has been blocked due to too many failed attempts.';
            }

            return response()->json(['message' => $message], 400);
        }

        $recipient = User::find($confirmation->recipient_id);

        if (!$recipient) {
            return response()->json([
                'message' => 'Recipient not found.',
            ], 404);
        }

        $result = $action->execute(
            $sender,
            $recipient,
            $confirmation->amount
        );

        $confirmation->update([
            'confirmed' => true,
            'confirmed_at' => now(),
        ]);

        return response()->json(new TransferResource($result));
    }
}
