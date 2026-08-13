<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaApiController extends Controller
{
    public function index(Request $request)
    {
        $wsId = $request->header('X-Workspace-ID') ?: session('active_workspace_id');

        $media = Media::when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->latest()
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $media,
        ]);
    }

    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:20480',
        ]);

        $file = $request->file('file');
        $wsId = $request->header('X-Workspace-ID') ?: session('active_workspace_id');

        $path = $file->store('media', 'public');
        $media = Media::create([
            'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'disk' => 'public',
            'size' => $file->getSize(),
            'path' => Storage::url($path),
            'workspace_id' => $wsId,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'success' => true,
            'media' => $media,
        ], 201);
    }
}
