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
        $files = collect(File::glob(base_path('EE/sam_export*.csv')))
            ->filter(static fn (string $path): bool => File::exists($path))
            ->map(function (string $path): array {
                $size = File::size($path);
                $rowCount = $this->countCsvRows($path);

                return [
                    'name' => basename($path),
                    'size_bytes' => $size,
                    'size_human' => $this->formatBytes($size),
                    'row_count' => $rowCount,
                    'modified_at' => Carbon::createFromTimestamp(File::lastModified($path)),
                ];
            })
            ->sortByDesc(static fn (array $file): int => $file['modified_at']->getTimestamp())
            ->values();

        $totalRows = $files->sum('row_count');
        $totalSize = $files->sum('size_bytes');

        $latestFile = $files->first();
        [$previewHeader, $previewRows, $previewRowCount] = $latestFile
            ? $this->previewCsv(base_path('EE/' . $latestFile['name']))
            : [[], [], 0];

        return view('admin.lending-tower.index', [
            'files' => $files,
            'latestFile' => $latestFile,
            'previewHeader' => $previewHeader,
            'previewRows' => $previewRows,
            'rowCount' => $latestFile['row_count'] ?? 0,
            'totalRows' => $totalRows,
            'totalSize' => $this->formatBytes($totalSize),
            'totalFiles' => $files->count(),
        ]);
    }

    public function download(string $file): BinaryFileResponse
    {
        $fileName = basename($file);

        if (! preg_match('/^sam_export[0-9A-Za-z._-]*\.csv$/', $fileName)) {
            abort(404);
        }

        $path = base_path('EE/' . $fileName);

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

    protected function countCsvRows(string $path): int
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return 0;
        }

        $count = 0;
        while (fgets($handle) !== false) {
            $count++;
        }
        fclose($handle);

        return max(0, $count - 1);
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
