@csrf
<div class="mb-3">
    <label for="name" class="form-label">Name</label>
    <input type="text" name="name" id="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $automation->name ?? '') }}" required>
    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="slug" class="form-label">Slug (optional)</label>
    <input type="text" name="slug" id="slug" class="form-control @error('slug') is-invalid @enderror" value="{{ old('slug', $automation->slug ?? '') }}">
    @error('slug')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="command" class="form-label">Command</label>
    <input type="text" name="command" id="command" class="form-control @error('command') is-invalid @enderror" placeholder="example: reports:generate" value="{{ old('command', $automation->command ?? '') }}" required>
    @error('command')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="mb-3">
    <label for="cron_expression" class="form-label">Cron Expression</label>
    <input type="text" name="cron_expression" id="cron_expression" class="form-control @error('cron_expression') is-invalid @enderror" placeholder="*/5 * * * *" value="{{ old('cron_expression', $automation->cron_expression ?? '') }}" required>
    @error('cron_expression')<div class="invalid-feedback">{{ $message }}</div>@enderror
</div>

<div class="row g-3">
    <div class="col-md-4">
        <label for="timeout_seconds" class="form-label">Timeout (seconds)</label>
        <input type="number" name="timeout_seconds" id="timeout_seconds" class="form-control @error('timeout_seconds') is-invalid @enderror" value="{{ old('timeout_seconds', $automation->timeout_seconds ?? '') }}" min="1">
        @error('timeout_seconds')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4">
        <label for="run_via" class="form-label">Run via</label>
        <select name="run_via" id="run_via" class="form-select @error('run_via') is-invalid @enderror">
            @foreach (['artisan' => 'Artisan', 'later' => 'Queue/Later'] as $value => $label)
                <option value="{{ $value }}" @selected(old('run_via', $automation->run_via ?? 'artisan') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        @error('run_via')<div class="invalid-feedback">{{ $message }}</div>@enderror
    </div>
    <div class="col-md-4 d-flex align-items-center gap-3">
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" value="1" id="is_active" name="is_active" @checked(old('is_active', $automation->is_active ?? true))>
            <label class="form-check-label" for="is_active">Active</label>
        </div>
        <div class="form-check mt-4">
            <input class="form-check-input" type="checkbox" value="1" id="notify_on_fail" name="notify_on_fail" @checked(old('notify_on_fail', $automation->notify_on_fail ?? false))>
            <label class="form-check-label" for="notify_on_fail">Notify on fail</label>
        </div>
    </div>
</div>
