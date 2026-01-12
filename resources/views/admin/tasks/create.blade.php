<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <h2 class="h4 mb-0">Create Task</h2>
                <small class="text-muted">Add a new task to track work.</small>
            </div>
            <a href="{{ route('admin.tasks.index', request()->query()) }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to tasks
            </a>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0 mx-auto" style="max-width: 900px;">
        <div class="card-body">
            <form action="{{ route('admin.tasks.store') }}" method="POST" enctype="multipart/form-data">
                @csrf
                @include('admin.tasks._form')
                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.tasks.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
