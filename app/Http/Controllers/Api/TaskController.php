<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TaskService\TaskServiceRequest;
use App\Models\TaskService;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class TaskController extends Controller
{
    use ApiResponseTraits;

    public function create(TaskServiceRequest $request): JsonResponse
    {
        $taskService = TaskService::create(array_merge($request->validated(), [
            'user_id' => Auth::id() ?? 1,
            'status' => 'new',
        ]));

        return $this->successResponse($taskService, 'Task service created successfully.', 201);
    }

    public function update(TaskServiceRequest $request, $id): JsonResponse
    {
        $taskService = TaskService::findOrFail($id);
        $taskService->update($request->validated());

        return $this->successResponse($taskService, 'Task service updated successfully.', 200);
    }

    public function delete($id): JsonResponse
    {
        $taskService = TaskService::findOrFail($id);
        $taskService->delete();

        return $this->successResponse(null, 'Task service deleted successfully.', 200);
    }

    public function getAll(): JsonResponse
    {
        $taskServices = TaskService::all();

        return $this->successResponse($taskServices, 'Task services fetched successfully.', 200);
    }

    public function details($id): JsonResponse
    {
        $taskService = TaskService::findOrFail($id);

        return $this->successResponse($taskService, 'Task service details fetched successfully.', 200);
    }

    public function getMyTasks(): JsonResponse
    {
        $user = Auth::user();
        $attribute = $user->role === 'runner' ? 'runner_id' : 'user_id';
        $taskServices = TaskService::where($attribute, Auth::id())->get();

        return $this->successResponse($taskServices, 'Task services fetched successfully.', 200);
    }

    public function runnerAcceptTask($id): JsonResponse
    {
        $taskService = TaskService::findOrFail($id);
        $taskService->update([
            'status' => 'pending',
            'runner_id' => Auth::id(),
        ]);

        return $this->successResponse($taskService, 'Task service accepted successfully.', 200);
    }

    public function runnerRejectTask($id): JsonResponse
    {
        $taskService = TaskService::findOrFail($id);
        $taskService->update([
            'status' => 'rejected',
        ]);

        return $this->successResponse($taskService, 'Task service rejected successfully.', 200);
    }

    public function runnerCompleteTask($id): JsonResponse
    {
        $taskService = TaskService::findOrFail($id);
        $taskService->update([
            'status' => 'completed',
        ]);

        return $this->successResponse($taskService, 'Task service completed successfully.', 200);
    }
}
