<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MediaDirectory;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
            'directory_id' => ['nullable', Rule::exists('media_directories', 'id')->where('workspace_id', session('active_workspace_id'))],
        ]);

        $wsId = session('active_workspace_id');
        $uploaded = [];

        foreach ($request->file('files') as $file) {
            $disk = config('filesystems.default', 'local');
            $path = $file->store("workspaces/{$wsId}/media", $disk);
            $media = Media::create([
                'name' => pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'disk' => $disk,
                'size' => $file->getSize(),
                'path' => $path,
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

        Storage::disk($media->disk)->delete($media->path);
        $media->delete();

        return response()->json(['success' => true]);
    }

    public function createDirectory(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => ['nullable', Rule::exists('media_directories', 'id')->where('workspace_id', session('active_workspace_id'))],
        ]);

        $wsId = session('active_workspace_id');

        $directory = MediaDirectory::create([
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?? null,
            'disk' => config('filesystems.default', 'local'),
            'workspace_id' => $wsId,
            'created_by' => Auth::id(),
        ]);

        return response()->json(['success' => true, 'directory' => $directory]);
    }

    public function updateDirectory(Request $request, MediaDirectory $directory)
    {
        $this->authorizeDirectory($directory);
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => [
                'nullable',
                Rule::exists('media_directories', 'id')->where('workspace_id', session('active_workspace_id')),
                Rule::notIn([$directory->id]),
            ],
        ]);

        $directory->update($validated);

        return response()->json(['success' => true, 'directory' => $directory]);
    }

    public function destroyDirectory(MediaDirectory $directory)
    {
        $this->authorizeDirectory($directory);
        Media::where('workspace_id', session('active_workspace_id'))
            ->where('directory_id', $directory->id)
            ->update(['directory_id' => null]);
        MediaDirectory::where('workspace_id', session('active_workspace_id'))
            ->where('parent_id', $directory->id)
            ->update(['parent_id' => $directory->parent_id]);
        $directory->delete();

        return response()->json(['success' => true]);
    }

    public function updateMediaDirectory(Request $request)
    {
        $validated = $request->validate([
            'media_ids' => 'required|array|max:100',
            'media_ids.*' => 'integer',
            'directory_id' => ['nullable', Rule::exists('media_directories', 'id')->where('workspace_id', session('active_workspace_id'))],
        ]);

        $updated = Media::where('workspace_id', session('active_workspace_id'))
            ->whereIn('id', $validated['media_ids'])
            ->update(['directory_id' => $validated['directory_id']]);

        abort_unless($updated === count(array_unique($validated['media_ids'])), 404);

        return response()->json(['success' => true]);
    }

    public function download(Media $media)
    {
        $this->authorizeMedia($media);
        abort_unless(Storage::disk($media->disk)->exists($media->path), 404);

        return Storage::disk($media->disk)->download($media->path, $media->file_name);
    }

    public function preview(Media $media)
    {
        $this->authorizeMedia($media);

        return response()->json([
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'download_url' => route('media.download', $media),
        ]);
    }

    private function authorizeMedia(Media $media): void
    {
        abort_unless((int) $media->workspace_id === (int) session('active_workspace_id'), 404);
    }

    private function authorizeDirectory(MediaDirectory $directory): void
    {
        abort_unless((int) $directory->workspace_id === (int) session('active_workspace_id'), 404);
    }
}
