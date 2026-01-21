@php
    $task = $task ?? null;
    $returnFilters = request()->query();
@endphp

<div class="row g-3">
    <input type="hidden" name="return_to" value="{{ request()->input('return_to', '') }}">
    <input type="hidden" name="return_filters[search]" value="{{ $returnFilters['search'] ?? '' }}">
    <input type="hidden" name="return_filters[status]" value="{{ $returnFilters['status'] ?? '' }}">
    <input type="hidden" name="return_filters[priority]" value="{{ $returnFilters['priority'] ?? '' }}">
    <input type="hidden" name="return_filters[assigned_to]" value="{{ $returnFilters['assigned_to'] ?? '' }}">
    <input type="hidden" name="return_filters[category_id]" value="{{ $returnFilters['category_id'] ?? '' }}">
    <input type="hidden" name="return_filters[per_page]" value="{{ $returnFilters['per_page'] ?? '' }}">

    <div class="col-12">
        <label class="form-label" for="title">Title</label>
        <input
            type="text"
            name="title"
            id="title"
            class="form-control @error('title') is-invalid @enderror"
            value="{{ old('title', $task->title ?? '') }}"
            required
        >
        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12">
        <label class="form-label" for="description">Description</label>
        <textarea
            name="description"
            id="description"
            rows="4"
            class="form-control @error('description') is-invalid @enderror"
        >{{ old('description', $task->description ?? '') }}</textarea>
        @error('description')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label" for="status">Status</label>
        @php
            $statusValue = old('status', $task->status ?? \App\Models\Task::STATUS_TODO);
            $statusOptions = $statusOptions ?? \App\Models\Task::formStatusLabels();
            $canChangeStatus = $task ? auth()->user()?->can('changeStatus', $task) : true;
        @endphp
        @if (! $canChangeStatus)
            <input type="hidden" name="status" value="{{ $statusValue }}">
            <input type="text" class="form-control" value="{{ $statusOptions[$statusValue] ?? $statusValue }}" disabled>
        @else
            <select name="status" id="status" class="form-select @error('status') is-invalid @enderror">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                @endforeach
            </select>
        @endif
        @error('status')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label" for="priority">Priority</label>
        <select name="priority" id="priority" class="form-select @error('priority') is-invalid @enderror">
            @php $priorityValue = old('priority', $task->priority ?? 'medium'); @endphp
            <option value="low" @selected($priorityValue === 'low')>Low</option>
            <option value="medium" @selected($priorityValue === 'medium')>Medium</option>
            <option value="high" @selected($priorityValue === 'high')>High</option>
        </select>
        @error('priority')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-4">
        <label class="form-label" for="due_at">Due at</label>
        <input
            type="datetime-local"
            name="due_at"
            id="due_at"
            class="form-control @error('due_at') is-invalid @enderror"
            value="{{ old('due_at', isset($task->due_at) ? $task->due_at->format('Y-m-d\\TH:i') : '') }}"
        >
        @error('due_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

	    <div class="col-12 col-md-6">
	        <label class="form-label" for="assigned_to">Assigned to</label>
	        @php
	            $assignedToValue = (string) old('assigned_to', $task ? ($task->assigned_to ?? '') : '');
	            $canAssign = $task ? auth()->user()?->can('assign', $task) : auth()->user()?->can('assign-task');
	        @endphp
	        @if (! $canAssign)
	            @php
	                if (! $task) {
	                    $assignedToValue = '';
	                }
	            @endphp
	            <input type="hidden" name="assigned_to" value="{{ $assignedToValue }}">
	            @php
	                $assignedUser = $task?->assignedTo;
	                $assignedLabel = $assignedUser ? ($assignedUser->name . ' (' . $assignedUser->email . ')') : 'Unassigned';
            @endphp
            <input type="text" class="form-control" value="{{ $assignedLabel }}" disabled>
        @else
            <select name="assigned_to" id="assigned_to" class="form-select @error('assigned_to') is-invalid @enderror">
                <option value="">Unassigned</option>
                @foreach ($users as $user)
                    <option
                        value="{{ $user->id }}"
                        @selected($assignedToValue === (string) $user->id)
                    >
                        {{ $user->name }} ({{ $user->email }})
                    </option>
                @endforeach
            </select>
        @endif
        @error('assigned_to')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    <div class="col-12 col-md-6">
        <label class="form-label" for="category_id">Category</label>
        <select
            name="category_id"
            id="category_id"
            class="form-select @error('category_id') is-invalid @enderror"
            data-label-select
        >
            @php
                $selectedCategory = old('category_id', $task?->labels->first()?->id);
            @endphp
            <option value="">No category</option>
            @foreach ($categories as $category)
                <option
                    value="{{ $category->id }}"
                    data-color="{{ $category->color }}"
                    @selected((string) $selectedCategory === (string) $category->id)
                >
                    {{ $category->name }}
                </option>
            @endforeach
        </select>
        <div class="form-text">Select a category.</div>
        <div class="d-flex flex-wrap gap-1 mt-2" data-label-preview></div>
        @error('category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>

    @php
        $canUploadAttachments = $task ? auth()->user()?->can('uploadAttachments', $task) : auth()->user()?->can('upload-task-attachments');
    @endphp
    @if ($canUploadAttachments)
        <div class="col-12">
            <label class="form-label" for="attachments">Attachments</label>
            <input
                type="file"
                name="attachments[]"
                id="attachments"
                class="form-control @error('attachments') is-invalid @enderror"
                multiple
            >
            <div class="form-text">Add one or more files (max 5MB each).</div>
            @error('attachments')<div class="invalid-feedback">{{ $message }}</div>@enderror
            @error('attachments.*')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
        </div>
    @endif

    @php
        $canComment = $task ? auth()->user()?->can('comment', $task) : auth()->user()?->can('comment-task');
    @endphp
    @if ($canComment)
        <div class="col-12">
            <label class="form-label" for="comment">Add comment</label>
            <textarea
                name="comment"
                id="comment"
                rows="3"
                class="form-control @error('comment') is-invalid @enderror"
                placeholder="Leave a note for this task..."
            >{{ old('comment') }}</textarea>
            @error('comment')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    @endif
</div>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('[data-label-select]').forEach((select) => {
                const preview = select.closest('div')?.querySelector('[data-label-preview]');
                if (!preview) return;

                const render = () => {
                    preview.innerHTML = '';
                    Array.from(select.selectedOptions).forEach((option) => {
                        const chip = document.createElement('span');
                        const color = option.dataset.color || '#e2e8f0';
                        const textColor = color.toUpperCase() === '#F59E0B' ? '#0f172a' : '#fff';
                        chip.className = 'badge';
                        chip.style.backgroundColor = color;
                        chip.style.color = textColor;
                        chip.textContent = option.textContent.trim();
                        preview.appendChild(chip);
                    });
                };

                select.addEventListener('change', render);
                render();
            });
        });
    </script>
@endpush
