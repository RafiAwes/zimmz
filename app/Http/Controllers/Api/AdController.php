<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ad;
use App\Traits\ApiResponseTraits;
use App\Traits\ImageTrait;

class AdController extends Controller
{
    use ApiResponseTraits, ImageTrait;

    public function create(Request $request)
    {
        $request->validate([
            'banner' => 'required|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $bannerPath = $this->uploadAvatar($request, 'banner', 'images/ads');

        $ad = Ad::create([
            'banner' => $bannerPath,
        ]);

        return $this->successResponse($ad, 'Ad banner created successfully.', 201);
    }

    public function getAll(Request $request)
    {
        $per_page = $request->per_page ?? 5;
        $ads = Ad::paginate($per_page);

        return $this->successResponse($ads, 'Ad banners fetched successfully.', 200);
    }

    public function details(Request $request, $id)
    {
        $ad = Ad::findOrFail($id);
        return $this->successResponse($ad, 'Ad banner details fetched successfully.', 200);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'banner' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $ad = Ad::findOrFail($id);

        if ($request->hasFile('banner')) {
            // Delete old image
            $oldBanner = $ad->getRawOriginal('banner');
            if ($oldBanner) {
                $this->deleteImage($oldBanner);
            }

            // Upload new image
            $bannerPath = $this->uploadAvatar($request, 'banner', 'images/ads');
            $ad->update(['banner' => $bannerPath]);
        }

        return $this->successResponse($ad, 'Ad banner updated successfully.', 200);
    }

    public function delete(Request $request, $id)
    {
        $ad = Ad::findOrFail($id);

        $oldBanner = $ad->getRawOriginal('banner');
        if ($oldBanner) {
            $this->deleteImage($oldBanner);
        }

        $ad->delete();

        return $this->successResponse(null, 'Ad banner deleted successfully.', 200);
    }
}
