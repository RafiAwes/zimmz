<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ferry;
use App\Traits\ApiResponseTraits;

class FerryController extends Controller
{
    use ApiResponseTraits;
    public function create(Request $request)
    {
        $data = $request->validate([
            'island_id' => 'required|exists:islands,id',
            'name' => 'required|string|max:255',
            'days' => 'required|array',
            'times' => 'required|array',
        ]);

        $ferry = Ferry::create($data);

        return $this->successResponse($ferry, 'Ferry created successfully.', 201);
    }

    public function getAll(Request $request)
    {
        $per_page = $request->per_page ?? 5;

        $search = $request->search;
        $ferries = Ferry::where('name', 'like', "%{$search}%")->paginate($per_page);

        return $this->successResponse($ferries, 'Ferries fetched successfully.', 200);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'island_id' => 'required|exists:islands,id',
            'name' => 'required|string|max:255',
            'days' => 'required|array',
            'times' => 'required|array',
        ]);

        $ferry = Ferry::findOrFail($id);
        $ferry->update($data);

        return $this->successResponse($ferry, 'Ferry updated successfully.', 200);
    }

    public function delete(Request $request, $id)
    {
        $ferry = Ferry::findOrFail($id);
        $ferry->delete();

        return $this->successResponse(null, 'Ferry deleted successfully.', 200);
    }

    public function details(Request $request, $id)
    {
        $ferry = Ferry::with('island')->findOrFail($id);
        return $this->successResponse($ferry, 'Ferry details fetched successfully.', 200);
    }
}
