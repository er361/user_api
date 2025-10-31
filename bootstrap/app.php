<?php

use App\Exceptions\InsufficientBalanceException;
use App\Exceptions\InvalidTransferAmountException;
use App\Exceptions\SelfTransferException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Configure rate limiting
        $middleware->throttleApi('60,1'); // 60 requests per minute for general API
        $middleware->alias([
            'transfers' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':10,1', // 10 transfers per minute
            'admin' => \Illuminate\Routing\Middleware\ThrottleRequests::class.':30,1', // 30 admin requests per minute
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (InsufficientBalanceException $e, Request $request) {
            Log::warning('Insufficient balance', [
                'user_id' => $request->user()?->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        });

        $exceptions->render(function (SelfTransferException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        });

        $exceptions->render(function (InvalidTransferAmountException $e, Request $request) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 400);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Resource not found.',
                ], 404);
            }
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                Log::warning('Access denied', [
                    'user_id' => $request->user()?->id,
                    'url' => $request->fullUrl(),
                ]);

                return response()->json([
                    'message' => 'This action is unauthorized.',
                ], 403);
            }
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'The given data was invalid.',
                    'errors' => $e->errors(),
                ], 422);
            }
        });
    })->create();
