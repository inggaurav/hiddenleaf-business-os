<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MediaDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class MediaController extends Controller
{
    public function page()
    {
        $wsId = session('active_workspace_id');

        $directories = MediaDirectory::when($wsId, fn ($q) => $q->where('workspace_id', $wsId))->get();
        $media = Media::when($wsId, fn ($q) => $q->where('workspace_id', $wsId))->latest()->get();

        return Inertia::render('Media/Index', [
            'directories' => $directories,
            'media' => $media,
        ]);
    }

    public function index(Request $request)
    {
        $wsId = session('active_workspace_id');

        $media = Media::query()
            ->when($wsId, fn ($q) => $q->where('workspace_id', $wsId))
            ->when($request->directory_id, fn ($q) => $q->where('directory_id', $request->directory_id))
            ->when($request->search, fn ($q) => $q->where('name', 'like', "%{$request->search}%"))
            ->latest()
            ->paginate($request->input('per_page', 20))
            ->withQueryString();

        return response()->json($media);
    }

    public function batchStore(Request $request)
    {
        $request->validate([
            'files' => 'required|array',
            'files.*' => 'required|file|max:20480',
            'directory_id' => 'nullable|exists:media_directories,id',
        ]);

        $wsId = session('active_workspace_id');
        $uploaded = [];

        foreach ($request->file('files') as $file) {
            $path = $file->store('media', 'public');
            $media = Media::create([
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'disk' => 'public',
                'size' => $file->getSize(),
                'path' => Storage::url($path),
                'directory_id' => $request->directory_id,
                'workspace_id' => $wsId,
                'created_by' => Auth::id(),
            ]);
            $uploaded[] = $media;
        }

        return response()->json([
            'success' => true,
            'uploaded' => $uploaded,
        ]);
    }

    public function destroy(Media $media)
    {
        $wsId = session('active_workspace_id');
        if ($media->workspace_id && $media->workspace_id !== $wsId) {
            abort(403, 'Unauthorized.');
        }

        $media->delete();

        return response()->json(['success' => true]);
    }

    public function createDirectory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'path' => 'nullable|string',
        ]);

        $wsId = session('active_workspace_id');

        $directory = MediaDirectory::create([
            'name' => $validated['name'],
            'path' => $validated['path'] ?? null,
            'disk' => 'public',
            'workspace_id' => $wsId,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'directory' => $directory]);
    }

    public function updateDirectory(Request $request, MediaDirectory $directory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $directory->update($validated);

        return response()->json(['success' => true, 'directory' => $directory]);
    }

    public function destroyDirectory(MediaDirectory $directory)
    {
        Media::where('directory_id', $directory->id)->update(['directory_id' => null]);
        $directory->delete();

        return response()->json(['success' => true]);
    }

    public function updateMediaDirectory(Request $request)
    {
        $validated = $request->validate([
            'media_ids' => 'required|array',
            'directory_id' => 'nullable|exists:media_directories,id',
        ]);

        Media::whereIn('id', $validated['media_ids'])->update(['directory_id' => $validated['directory_id']]);

        return response()->json(['success' => true]);
    }
}
