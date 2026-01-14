<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\S3UploadJob;
use Illuminate\Http\Request;
use Illuminate\View\View;

class S3UploadJobController extends Controller
{
    public function index(Request $request): View
    {
        $query = S3UploadJob::query()->orderByDesc('created_at');

        $search = trim((string) $request->input('search', ''));
        $status = $request->input('status');
        $state = strtoupper(trim((string) $request->input('state', '')));

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('original_name', 'like', "%{$search}%")
                    ->orWhere('stored_path', 'like', "%{$search}%")
                    ->orWhere('s3_key', 'like', "%{$search}%")
                    ->orWhere('uploader', 'like', "%{$search}%")
                    ->orWhere('request_ip', 'like', "%{$search}%");
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
            S3UploadJob::STATUS_QUEUED,
            S3UploadJob::STATUS_PENDING,
            S3UploadJob::STATUS_PROCESSING,
            S3UploadJob::STATUS_UPLOADED,
            S3UploadJob::STATUS_FAILED,
        ];

        $filters = [
            'search' => $search,
            'status' => $status,
            'state' => $state,
        ];

        return view('admin.s3-upload-jobs.index', compact('jobs', 'statusOptions', 'filters'));
    }

    public function show(S3UploadJob $job): View
    {
        return view('admin.s3-upload-jobs.show', compact('job'));
    }
}
