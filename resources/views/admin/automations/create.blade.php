<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Admin</p>
                <h2 class="h4 mb-0">{{ __('Create Automation') }}</h2>
                <small class="text-muted">Define a new automation and schedule.</small>
            </div>
            <a href="{{ route('admin.automations.index') }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to automations
            </a>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('admin.automations.store') }}" method="POST">
                @include('admin.automations._form')
                <div class="mt-4 d-flex justify-content-end gap-2">
                    <a href="{{ route('admin.automations.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
