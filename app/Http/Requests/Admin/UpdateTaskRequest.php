<?php

namespace App\Http\Requests\Admin;

use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $task = $this->route('task');
        $allowedStatuses = Task::editStatuses($task instanceof Task ? $task : null);

        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in($allowedStatuses)],
            'priority' => ['required', 'in:low,medium,high'],
            'due_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'category_id' => ['nullable', 'integer', 'exists:task_labels,id'],
            'comment' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:5120'],
        ];
    }
}
