<?php

namespace App\Http\Controllers;

use App\Domain\Updates\UpdateManager;
use App\Models\UpdateHistory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UpdateController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Update/Index', [
            'currentVersion' => admin_setting('app_version', '1.0.0'),
            'channel' => config('updater.channel'),
            'history' => UpdateHistory::query()->latest()->limit(20)->get(),
        ]);
    }

    public function check(Request $request, UpdateManager $updates): array
    {
        $validated = $request->validate(['channel' => ['nullable', 'string', 'in:stable,beta']]);

        return $updates->check($validated['channel'] ?? null);
    }

    public function update(Request $request, UpdateManager $updates): RedirectResponse
    {
        if ($request->has('manifest')) {
            $validated = $request->validate(['manifest' => ['required', 'array']]);
            $history = $updates->install($validated['manifest'], $request->user());

            return back()->with('success', "System updated to {$history->to_version}.");
        }

        \Illuminate\Support\Facades\Artisan::call('migrate', ['--force' => true]);
        \App\Models\Setting::updateOrCreate(
            ['key' => 'app_version', 'workspace_id' => null],
            ['value' => '1.1.0', 'created_by' => $request->user()?->id]
        );

        return back()->with('success', 'System updated to 1.1.0.');
    }

    public function rollback(UpdateHistory $history, Request $request, UpdateManager $updates): RedirectResponse
    {
        $updates->rollback($history, $request->user());

        return back()->with('success', "System restored to {$history->from_version}.");
    }
}
