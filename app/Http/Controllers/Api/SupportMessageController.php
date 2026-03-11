<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\SupportMessage\ReplySupportMessageRequest;
use App\Http\Requests\Api\SupportMessage\StoreSupportMessageRequest;
use App\Mail\AdminReplyToSupportMessageMail;
use App\Mail\UserSupportMessageToAdminMail;
use App\Http\Controllers\Controller;
use App\Models\SupportMessage;
use App\Models\User;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class SupportMessageController extends Controller
{
    use ApiResponseTraits;

    public function send(StoreSupportMessageRequest $request): JsonResponse
    {
        try {
            $user = Auth::guard('api')->user();

            $supportMessage = SupportMessage::create([
                'user_id' => $user->id,
                'subject' => $request->subject,
                'message' => $request->message,
                'status' => 'new',
            ]);

            $supportMessage->load('user');

            $admins = User::query()
                ->where('role', 'admin')
                ->whereNotNull('email')
                ->get();

            foreach ($admins as $admin) {
                Mail::to($admin->email)->send(
                    (new UserSupportMessageToAdminMail($supportMessage, $admin))
                        ->replyTo($user->email, $user->name)
                );
            }

            return $this->successResponse($supportMessage, 'Support message sent successfully.', 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send support message.', 500, $e->getMessage());
        }
    }

    public function myMessages(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $search = $request->input('search');
        $userId = Auth::guard('api')->id();

        $messages = SupportMessage::query()
            ->where('user_id', $userId)
            ->when($search, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('subject', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhere('reply_subject', 'like', "%{$search}%")
                        ->orWhere('reply_message', 'like', "%{$search}%");
                });
            })
            ->latest()
            ->paginate($perPage);

        return $this->successResponse($messages, 'Your support messages fetched successfully.', 200);
    }

    public function adminGetAll(Request $request): JsonResponse
    {
        $perPage = $request->input('per_page', 10);
        $status = $request->input('status');
        $search = $request->input('search');

        $messages = SupportMessage::query()
            ->with(['user:id,name,email', 'repliedBy:id,name,email'])
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($search, function ($query, $search) {
                $query->where(function ($searchQuery) use ($search) {
                    $searchQuery->where('subject', 'like', "%{$search}%")
                        ->orWhere('message', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($userQuery) use ($search) {
                            $userQuery->where('name', 'like', "%{$search}%")
                                ->orWhere('email', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate($perPage);

        return $this->successResponse($messages, 'Support messages fetched successfully.', 200);
    }

    public function adminDetails(int $id): JsonResponse
    {
        $supportMessage = SupportMessage::query()
            ->with(['user:id,name,email', 'repliedBy:id,name,email'])
            ->findOrFail($id);

        return $this->successResponse($supportMessage, 'Support message details fetched successfully.', 200);
    }

    public function adminReply(ReplySupportMessageRequest $request, int $id): JsonResponse
    {
        try {
            $admin = Auth::guard('api')->user();

            $supportMessage = SupportMessage::query()
                ->with('user:id,name,email')
                ->findOrFail($id);

            Mail::to($supportMessage->user->email)
                ->send(new AdminReplyToSupportMessageMail(
                    $supportMessage,
                    $admin,
                    $request->subject,
                    $request->message
                ));

            $supportMessage->update([
                'status' => 'replied',
                'reply_subject' => $request->subject,
                'reply_message' => $request->message,
                'replied_by' => $admin->id,
                'replied_at' => now(),
            ]);

            return $this->successResponse(
                $supportMessage->fresh(['user:id,name,email', 'repliedBy:id,name,email']),
                'Reply sent successfully.',
                200
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send reply.', 500, $e->getMessage());
        }
    }
}
