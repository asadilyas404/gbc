<?php

namespace App\Exceptions;

use RuntimeException;

class ImageUploadException extends RuntimeException
{
    public function render($request)
    {
        if ($request->ajax() || $request->expectsJson() || $request->is('api/*')) {
            // Dashboard forms display the application's errors array in their success callback.
            return response()->json([
                'errors' => [['code' => 'image', 'message' => $this->getMessage()]],
            ], $request->is('api/*') ? 422 : 200);
        }

        \Brian2694\Toastr\Facades\Toastr::error($this->getMessage());
        return back()->withInput()->withErrors(['image' => $this->getMessage()]);
    }
}
