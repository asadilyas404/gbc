<?php

namespace App\Exceptions;

use Throwable;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that are not reported.
     *
     * @var array
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed for validation exceptions.
     *
     * @var array
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (\Exception $e, $request) {
            if ($e->getPrevious() instanceof \Illuminate\Session\TokenMismatchException) {
                // If AJAX request → return JSON instead of redirect
                if ($request->ajax() || $request->wantsJson()) {
                    return response()->json([
                        'redirect' => route('home'),
                        'error' => 'token_mismatch'
                    ], 419); // 419 = CSRF error
                }

                // Normal request → redirect as usual
                Toastr::error('Session has expired. Please try again.'); 
                return redirect()->route('home');
            }
        });
    }
}
