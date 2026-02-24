<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Restaurant;
use App\Traits\ApiResponseTraits;

class RestaurantController extends Controller
{
    use ApiResponseTraits;

    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'lat' => 'required|string',
            'long' => 'required|string',
        ]);

        $restaurant = Restaurant::create($data);

        return $this->successResponse($restaurant, 'Restaurant created successfully.', 201);
    }

    public function getAll(Request $request)
    {
        $per_page = $request->per_page ?? 5;
        $search = $request->search;

        $restaurants = Restaurant::when($search, function ($query, $search) {
            $query->where('name', 'like', "%{$search}%");
        })->paginate($per_page);

        return $this->successResponse($restaurants, 'Restaurants fetched successfully.', 200);
    }

    public function details(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);
        return $this->successResponse($restaurant, 'Restaurant details fetched successfully.', 200);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'lat' => 'sometimes|string',
            'long' => 'sometimes|string',
        ]);

        $restaurant = Restaurant::findOrFail($id);
        $restaurant->update($data);

        return $this->successResponse($restaurant, 'Restaurant updated successfully.', 200);
    }

    public function delete(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $restaurant->delete();

        return $this->successResponse(null, 'Restaurant deleted successfully.', 200);
    }
}
