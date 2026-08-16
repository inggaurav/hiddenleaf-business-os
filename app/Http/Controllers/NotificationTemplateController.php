<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSettings;
use App\Models\Language;
use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateLang;
use App\Services\LocalizedTemplateRenderer;
use App\Services\AddonManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationTemplateController extends Controller
{
    use AuthorizesSettings;

    public function __construct(private LocalizedTemplateRenderer $renderer, private AddonManager $addons) {}

    public function index(Request $request)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        $templates = NotificationTemplate::query()
            ->where(fn ($query) => $workspace ? $query->whereNull('workspace_id')->orWhere('workspace_id', $workspace->id) : $query)
            ->orderBy('module')->orderBy('name')->get()
            ->filter(fn (NotificationTemplate $template) => ! $workspace || $template->module === 'general' || $this->addons->canUse($workspace, $template->module, $request->user()->isSuperAdmin()))
            ->sortByDesc(fn (NotificationTemplate $template) => $template->workspace_id ? 1 : 0)
            ->unique(fn (NotificationTemplate $template) => $template->module.'|'.$template->name)->values();
        $languages = Language::where('status', true)->get();

        return Inertia::render('Settings/NotificationTemplates/Index', [
            'templates' => $templates,
            'languages' => $languages,
        ]);
    }

    public function show(NotificationTemplate $notificationTemplate, Request $request)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        $notificationTemplate = $this->localTemplate($notificationTemplate, $workspace?->id, $request->user()->id);
        $lang = $request->input('lang', 'en');
        $languages = Language::where('status', true)->get();

        $currTemplate = NotificationTemplateLang::firstOrCreate(
            ['parent_id' => $notificationTemplate->id, 'lang' => $lang],
            [
                'content' => 'New notification: {message}',
            ]
        );

        return Inertia::render('Settings/NotificationTemplates/Show', [
            'template' => $notificationTemplate,
            'currTemplate' => $currTemplate,
            'languages' => $languages,
            'currentLang' => $lang,
        ]);
    }

    public function update(Request $request, NotificationTemplate $notificationTemplate)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        $notificationTemplate = $this->localTemplate($notificationTemplate, $workspace?->id, $request->user()->id);
        $validated = $request->validate([
            'lang' => 'required|string',
            'content' => 'required|string',
            'is_enabled' => 'nullable|boolean',
        ]);

        NotificationTemplateLang::updateOrCreate(
            ['parent_id' => $notificationTemplate->id, 'lang' => $validated['lang']],
            [
                'content' => $validated['content'],
            ]
        );

        if (array_key_exists('is_enabled', $validated)) {
            $notificationTemplate->update(['is_enabled' => $validated['is_enabled']]);
        }

        return redirect()->back()->with('success', 'Notification template updated successfully.');
    }

    public function preview(Request $request, NotificationTemplate $notificationTemplate)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        $notificationTemplate = $this->localTemplate($notificationTemplate, $workspace?->id, $request->user()->id);
        $validated = $request->validate([
            'lang' => ['nullable', 'string', 'max:10'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:10000'],
        ]);

        return response()->json($this->renderer->notification(
            $notificationTemplate,
            $validated['lang'] ?? $request->user()->lang ?? 'en',
            $validated['variables'] ?? [],
        ));
    }

    public function reset(Request $request, NotificationTemplate $notificationTemplate)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        abort_if($workspace && (int) $notificationTemplate->workspace_id !== (int) $workspace->id, 404);
        $lang = $request->validate(['lang' => ['required', 'string', 'max:10']])['lang'];
        $notificationTemplate->templateLangs()->where('lang', $lang)->delete();

        return back()->with('success', 'Notification template language reset to its default.');
    }

    private function localTemplate(NotificationTemplate $template, ?int $workspaceId, int $userId): NotificationTemplate
    {
        if (! $workspaceId) {
            abort_unless($template->workspace_id === null, 404);
            return $template;
        }
        abort_unless($template->workspace_id === null || (int) $template->workspace_id === $workspaceId, 404);
        if ((int) $template->workspace_id === $workspaceId) {
            return $template;
        }

        return NotificationTemplate::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => $template->name, 'module' => $template->module],
            ['variables' => $template->variables, 'is_enabled' => $template->is_enabled, 'created_by' => $userId],
        );
    }
}
