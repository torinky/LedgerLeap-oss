<?php

namespace App\Http\Controllers;

use App\Models\LedgerDefine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class LedgerDefineBackgroundImageController extends Controller
{
    public function download(Request $request, int $ledgerDefineId, int $columnId)
    {
        Log::info('[LedgerDefineBackgroundImageController@download] Started.', [
            'ledger_define_id' => $ledgerDefineId,
            'column_id' => $columnId,
            'thumbnail' => $request->boolean('thumbnail'),
        ]);

        $ledgerDefine = LedgerDefine::withoutTenancy()->findOrFail($ledgerDefineId);

        if (! tenant('id') || (string) $ledgerDefine->tenant_id !== (string) tenant('id')) {
            abort(404, 'File Not Found');
        }

        Gate::authorize('update', $ledgerDefine);

        $column = collect($ledgerDefine->column_define ?? [])->firstWhere('id', $columnId);
        $filePath = data_get($column, 'file.path');
        $fileName = data_get($column, 'file.name', basename((string) $filePath));

        if (! $filePath) {
            abort(404, 'File Not Found');
        }

        $servedPath = $filePath;
        if ($request->boolean('thumbnail')) {
            $thumbnailPath = 'thumbnails/'.$filePath;
            if (Storage::disk('public')->exists($thumbnailPath)) {
                $servedPath = $thumbnailPath;
            }
        }

        if (! Storage::disk('public')->exists($servedPath)) {
            abort(404, 'File Not Found');
        }

        $mimeType = Storage::disk('public')->mimeType($servedPath) ?: 'application/octet-stream';

        return response()->file(Storage::disk('public')->path($servedPath), [
            'Content-Type' => $mimeType,
            'Content-Disposition' => 'inline; filename="'.$fileName.'"',
        ]);
    }
}

