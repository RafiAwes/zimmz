<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRunnerRequest;
use App\Models\Runner;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class RunnerController extends Controller
{
    use ApiResponseTraits;

    public function create(StoreRunnerRequest $request)
    {
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated) {
                $user = User::create([
                    'name' => $validated['name'],
                    'email' => $validated['email'],
                    'password' => Hash::make($validated['password']),
                    'contact_number' => $validated['phone'] ?? null,
                    'address' => $validated['location'] ?? null,
                    'role' => 'runner',
                    'email_verified_at' => now(), // Default to verified since created by admin
                    'is_active' => true,
                ]);

                $runner = Runner::create([
                    'user_id' => $user->id,
                    'category' => $validated['runner_category'],
                    'type' => $validated['runner_type'] ?? 'assigned',
                ]);

                return $this->successResponse($user->load('runner'), 'Runner created successfully.', 201);
            });
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create runner.', 500, $e->getMessage());
        }
    }

    public function getAll(Request $request)
    {
        $per_page = $request->per_page ?? 5;
        $search = $request->search;
        $type = $request->type;

        $runners = User::query()
            ->where('role', 'runner')
            ->with('runner')
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('contact_number', 'like', "%{$search}%");
                });
            })
            ->when($type, function ($query, $type) {
                $query->whereHas('runner', function ($q) use ($type) {
                    $q->where('type', $type);
                });
            })
            ->paginate($per_page);

        return $this->successResponse($runners, 'Runners fetched successfully.', 200);
    }
}
