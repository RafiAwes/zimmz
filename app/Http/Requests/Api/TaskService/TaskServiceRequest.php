<?php

namespace App\Http\Requests\Api\TaskService;

use Illuminate\Foundation\Http\FormRequest;

class TaskServiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isUpdate = $this->isMethod('PUT') || $this->isMethod('PATCH');

        return [
            'task' => $isUpdate ? 'sometimes|string' : 'required|string',
            'price' => $isUpdate ? 'sometimes|string' : 'required|string',
            // 'status' => 'sometimes|string|in:new,pending,completed,rejected',
        ];
    }
}
