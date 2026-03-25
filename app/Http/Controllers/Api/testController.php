<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class testController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(['message' => 'Test endpoint is working!']);
    }

    public function zimmzPlusTest(Request $request)
    {
        return response()->json(['message' => 'You have access to the Zimmz Plus protected endpoint!']);
    }
}
