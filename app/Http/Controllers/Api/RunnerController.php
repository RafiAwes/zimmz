<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\Request;

class RunnerController extends Controller
{
    use ApiResponseTraits;

    public function getAll(Request $request)
    {
        $per_page = $request->per_page ?? 5;
        $search = $request->search;
        $runners = User::where('role', 'runner')->where('name', 'like', "%{$search}%")->paginate($per_page);

        return $this->successResponse($runners, 'Runners fetched successfully.', 200);
    }
}
