<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\TaskService\TaskServiceRequest;
use App\Models\TaskService;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use App\Traits\NotificationTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    use ApiResponseTraits;
    use NotificationTrait;

    public function create(TaskServiceRequest $request): JsonResponse
    {
        $taskService = TaskService::create(array_merge($request->validated(), [
            'user_id' => Auth::id() ?? 1,
            'status' => 'new',
        ]));

        $registeredRunnerIds = User::query()
            ->where('role', 'runner')
            ->whereHas('runner', function ($query) {
                $query->where('type', 'registered');
            })
            ->pluck('id')
            ->all();

        $creatorName = Auth::user()?->name ?? 'A user';

        $this->notifyUsers(
            $registeredRunnerIds,
            'New Task For You.',
            "A new task service order #{$taskService->id} has been placed by {$creatorName}.",
            'task_created'
        );

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

    public function getAll(Request $request): JsonResponse
    {
        $search = $request->search;
        $status = $request->status;
        $per_page = $request->per_page ?? 5;

        $taskServices = TaskService::with('user')
            ->when($search, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('task', 'like', "%{$search}%")
                        ->orWhere('price', 'like', "%{$search}%");
                });
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->paginate($per_page);

        return $this->successResponse($taskServices, 'Task services fetched successfully.', 200);
    }

    public function details($id): JsonResponse
    {
        $taskService = TaskService::findOrFail($id);
        $user = User::findOrFail($taskService->user_id);
        // $runner = User::findOrFail($taskService->runner_id);

        return $this->successResponse([
            'taskService' => $taskService,
            'user' => $user,
            // 'runner' => $runner,
        ], 'Task service details fetched successfully.', 200);
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
            'status' => 'pending_approval',
        ]);

        return $this->successResponse($taskService, 'Task service completed. Awaiting user approval.', 200);
    }

    public function approveTask($id): JsonResponse
    {
        $viewer = Auth::guard('api')->user();
        $taskService = TaskService::findOrFail($id);

        if ($taskService->user_id !== $viewer->id) {
            return $this->errorResponse('You are not authorized to approve this task.', 403);
        }

        if ($taskService->status !== 'pending_approval') {
            return $this->errorResponse('This task is not awaiting your approval.', 422);
        }

        DB::transaction(function () use ($taskService) {
            $taskService->update([
                'status' => 'completed',
            ]);

            // Capture payment if authorized
            $transaction = $taskService->transactions()->where('status', 'authorized')->first();
            if ($transaction) {
                app(CheckoutController::class)->capturePayment($transaction->payment_intent_id);
            }
        });

        $taskService->refresh();

        return $this->successResponse($taskService, 'Task approved. Payment captured and task completed.', 200);
    }
}
