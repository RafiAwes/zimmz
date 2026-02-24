<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Island;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\Request;

class IslandController extends Controller
{
    use ApiResponseTraits;

    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $island = Island::create($data);

        return $this->successResponse($island, 'Island created successfully.', 201);
    }

    public function getAll(Request $request)
    {
        $per_page = $request->per_page ?? 5;

        $search = $request->search;
        $islands = Island::where('name', 'like', "%{$search}%")->paginate($per_page);

        return $this->successResponse($islands, 'Islands fetched successfully.', 200);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $island = Island::findOrFail($id);
        $island->update($data);

        return $this->successResponse($island, 'Island updated successfully.', 200);
    }

    public function delete(Request $request, $id)
    {
        $island = Island::findOrFail($id);
        $island->delete();

        return $this->successResponse(null, 'Island deleted successfully.', 200);
    }

    public function details(Request $request, $id)
    {
        $island = Island::with('ferries')->findOrFail($id);
        return $this->successResponse($island, 'Island details fetched successfully.', 200);
    }
}
