<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageUpdateLog;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PackageUpdateController extends Controller
{
    public function index(): View
    {
        $logs = PackageUpdateLog::query()
            ->latest()
            ->paginate(20);

        return view('admin.package-updates.index', [
            'logs' => $logs,
        ]);
    }

    public function run(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'package' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9_.-]+\\/[a-z0-9_.-]+$/i'],
        ]);

        $startedAt = now();

        $exitCode = Artisan::call('package:update', [
            'name' => $validated['package'],
            '--triggered-by' => (string) (auth()->user()?->email ?: 'web'),
        ]);

        $last = PackageUpdateLog::query()
            ->where('created_at', '>=', $startedAt)
            ->latest()
            ->first();

        $status = $last?->status ?: ($exitCode === 0 ? 'success' : 'failure');
        $message = 'Package update finished: '.$status.($last?->id ? " (Log #{$last->id})" : '');

        return back()->with('status', $message);
    }
}
