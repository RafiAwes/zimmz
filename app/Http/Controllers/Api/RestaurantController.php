<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Restaurant\StoreRestaurantRequest;
use App\Http\Requests\Api\Restaurant\UpdateRestaurantRequest;
use App\Models\Restaurant;
use App\Traits\ApiResponseTraits;
use App\Traits\LocationTrait;
use Illuminate\Http\Request;

class RestaurantController extends Controller
{
    use ApiResponseTraits;
    use LocationTrait;

    public function create(StoreRestaurantRequest $request)
    {
        $locationData = $this->getLocationData($request->validated());

        $restaurant = Restaurant::create([
            'name' => $request->name,
            ...$locationData,
        ]);

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

    public function update(UpdateRestaurantRequest $request, $id)
    {
        $locationData = array_filter(
            $this->getLocationData($request->validated()),
            fn ($value) => $value !== null,
        );

        $restaurant = Restaurant::findOrFail($id);
        $restaurant->update(array_merge(
            $request->only(['name']),
            $locationData,
        ));

        return $this->successResponse($restaurant, 'Restaurant updated successfully.', 200);
    }

    public function delete(Request $request, $id)
    {
        $restaurant = Restaurant::findOrFail($id);
        $restaurant->delete();

        return $this->successResponse(null, 'Restaurant deleted successfully.', 200);
    }
}
