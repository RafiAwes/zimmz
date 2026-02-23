<?php

namespace App\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

trait ImageTrait
{
    public function uploadAvatar(Request $request, $inputName, $path)
    {
        if ($request->hasFile($inputName)) {
            $file = $request->file($inputName);
            $fileName = time().'_'.$file->getClientOriginalName();

            // Store in public disk (storage/app/public)
            $storedPath = Storage::disk('public')->putFileAs($path, $file, $fileName);

            if ($storedPath) {
                return $storedPath; // Return just the relative path inside 'storage/app/public'
            }
        }

        return null;
    }

    public function uploadImage(Request $request, $inputName, $path)
    {
        if ($request->hasFile($inputName)) {
            try {
                $file = $request->file($inputName);

                // Validate file
                if (! $file->isValid()) {
                    throw new \Exception('File upload failed');
                }

                $fileName = time().'_'.$file->getClientOriginalName();

                // Store in public disk (storage/app/public)
                $storedPath = Storage::disk('public')->putFileAs($path, $file, $fileName);

                if (! $storedPath) {
                    throw new \Exception('File could not be saved to storage path: '.$path);
                }

                // Return public URL path
                return 'storage/'.$storedPath;
            } catch (\Exception $e) {
                Log::error('Image upload failed: '.$e->getMessage());
                throw $e;
            }
        }

        throw new \Exception('No file provided in request field: '.$inputName);
    }

    public function deleteImage($imagePath)
    {
        if (Storage::disk('public')->exists($imagePath)) {
            Storage::disk('public')->delete($imagePath);
        }
    }
}
