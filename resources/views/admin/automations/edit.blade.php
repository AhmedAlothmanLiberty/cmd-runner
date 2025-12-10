<x-app-layout>
    <x-slot name="header">
        <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between w-100">
            <div>
                <p class="text-uppercase text-muted small fw-semibold mb-1">Admin</p>
                <h2 class="h4 mb-0">{{ __('Edit Automation') }}</h2>
                <small class="text-muted">Update automation settings for {{ $automation->name }}.</small>
            </div>
            <a href="{{ route('admin.automations.index') }}" class="btn btn-outline-secondary mt-3 mt-md-0">
                <i class="bi bi-arrow-left me-1"></i> Back to automations
            </a>
        </div>
    </x-slot>

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form id="automation-update-form" action="{{ route('admin.automations.update', $automation) }}" method="POST">
                @csrf
                @method('PUT')
                @include('admin.automations._form', ['automation' => $automation])
            </form>
            <div class="mt-4 d-flex justify-content-between align-items-center">
                <form action="{{ route('admin.automations.destroy', $automation) }}" method="POST" onsubmit="return confirm('Delete this automation?');" class="m-0">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-outline-danger btn-sm">Delete</button>
                </form>
                <div class="d-flex gap-2">
                    <a href="{{ route('admin.automations.index') }}" class="btn btn-outline-secondary">Cancel</a>
                    <button form="automation-update-form" type="submit" class="btn btn-primary">Save changes</button>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
