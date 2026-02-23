<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Island;

class IslandController extends Controller
{
    public function create(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $island = Island::create($data);

        return response()->json([
            'message' => 'Island created successfully.',
            'data' => $island,
        ], 201);
    }

    public function getAll(Request $request)
    {
        $islands = Island::all();

        return response()->json([
            'message' => 'Islands fetched successfully.',
            'data' => $islands,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $island = Island::findOrFail($id);
        $island->update($data);

        return response()->json([
            'message' => 'Island updated successfully.',
            'data' => $island,
        ], 200);
    }

    public function delete(Request $request, $id)
    {
        $island = Island::findOrFail($id);
        $island->delete();

        return response()->json([
            'message' => 'Island deleted successfully.',
        ], 200);
    }
}
