<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role'               => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'         => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => 'No autenticado. Token ausente o inválido.',
                    'data'    => null,
                ], 401);
            }
        });

        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, $request) {
            if ($request->is('api/*')) {
                $codigo = auth()->check() ? 403 : 401;
                return response()->json([
                    'status'  => 'error',
                    'message' => auth()->check()
                        ? 'No tienes permisos suficientes para realizar esta acción.'
                        : 'No autenticado.',
                    'data'    => null,
                ], $codigo);
            }
        });

        $exceptions->render(function (ValidationException $e, $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'status'  => 'error',
                    'message' => $e->getMessage(),
                    'errors'  => $e->errors(),
                    'data'    => ['errores' => $e->errors()],
                ], $e->status);
            }
        });

        $exceptions->render(function (Throwable $e, $request) {
            if (! $request->is('api/*') || $e instanceof ValidationException) {
                return null;
            }

            $codigo = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;

            return response()->json([
                'status'  => 'error',
                'message' => $e->getMessage() ?: 'Ocurrió un error inesperado.',
                'data'    => null,
            ], $codigo);
        });
    })->create();
