<?php

namespace App\Services;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class UploadPdfService extends Controller
{
    public function upload(Request $request)
    {
        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:10240',
            'secret_key' => 'required|string',
        ]);

        // Simple security check
        if ($request->secret_key !== config('services.sync_api.token')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);
        }

        $folderPath = public_path('uploads/attachments');

        if (!File::exists($folderPath)) {
            File::makeDirectory($folderPath, 0755, true);
        }

        $file = $request->file('pdf');

        $fileName = time() . '_' . uniqid() . '.pdf';

        $file->move($folderPath, $fileName);

        $fileUrl = asset('uploads/attachments/' . $fileName);

        return response()->json([
            'success' => true,
            'file_name' => $fileName,
            'url' => $fileUrl,
        ]);
    }
}