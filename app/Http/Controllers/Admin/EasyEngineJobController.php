<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EasyEngineJob;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EasyEngineJobController extends Controller
{
    public function index(Request $request): View
    {
        $query = EasyEngineJob::query()
            ->with('user')
            ->orderByDesc('created_at');

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $state = strtoupper(trim((string) $request->input('state', '')));

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhere('csv_path', 'like', "%{$search}%")
                    ->orWhere('parquet_path', 'like', "%{$search}%")
                    ->orWhere('s3_key', 'like', "%{$search}%");
            });
        }

        if (! empty($status)) {
            $query->where('status', $status);
        }

        if ($state !== '') {
            $query->where('state', $state);
        }

        $jobs = $query->paginate(25)->appends($request->query());

        $statusOptions = [
            'uploaded',
            'converted',
            'uploaded_s3',
            'failed',
        ];

        $filters = [
            'search' => $search,
            'status' => $status,
            'state' => $state,
        ];

        return view('admin.easyengine-jobs.index', compact('jobs', 'statusOptions', 'filters'));
    }

    public function show(EasyEngineJob $job): View
    {
        return view('admin.easyengine-jobs.show', compact('job'));
    }
}
