<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LendingTowerReportController extends Controller
{
    public function index(): View
    {
        $files = collect(File::glob(base_path('scripts/lending_tower/sms_report*.csv')))
            ->filter(static fn (string $path): bool => File::exists($path))
            ->map(function (string $path): array {
                $size = File::size($path);

                return [
                    'name' => basename($path),
                    'size_bytes' => $size,
                    'size_human' => $this->formatBytes($size),
                    'modified_at' => Carbon::createFromTimestamp(File::lastModified($path)),
                ];
            })
            ->sortByDesc(static fn (array $file): int => $file['modified_at']->getTimestamp())
            ->values();

        $latestFile = $files->first();
        [$previewHeader, $previewRows, $rowCount] = $latestFile
            ? $this->previewCsv(base_path('scripts/lending_tower/' . $latestFile['name']))
            : [[], [], 0];

        return view('admin.lending-tower.index', [
            'files' => $files,
            'latestFile' => $latestFile,
            'previewHeader' => $previewHeader,
            'previewRows' => $previewRows,
            'rowCount' => $rowCount,
        ]);
    }

    public function download(string $file): BinaryFileResponse
    {
        $fileName = basename($file);

        if (! preg_match('/^sms_report[0-9A-Za-z._-]*\.csv$/', $fileName)) {
            abort(404);
        }

        $path = base_path('scripts/lending_tower/' . $fileName);

        if (! File::exists($path)) {
            abort(404);
        }

        return response()->download($path, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function previewCsv(string $path, int $limit = 15): array
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return [[], [], 0];
        }

        $header = fgetcsv($handle) ?: [];
        $rows = [];
        $rowCount = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;

            if (count($rows) >= $limit || count($header) === 0) {
                continue;
            }

            $padded = array_slice(array_pad($row, count($header), ''), 0, count($header));
            $rows[] = array_combine($header, $padded) ?: [];
        }

        fclose($handle);

        return [$header, $rows, $rowCount];
    }

    protected function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }

        return number_format($bytes / (1024 * 1024), 1) . ' MB';
    }
}
