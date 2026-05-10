<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Stancl\Tenancy\Exceptions\RouteIsMissingTenantParameterException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (RouteIsMissingTenantParameterException $e, $request) {
            // HTMLレスポンスを期待するリクエストの場合のみリダイレクト
            if (! $request->expectsJson()) {
                return redirect()->route('login')
                    ->with('info', __('messages.login_again_for_tenant'));
            }
        });
    }
}
