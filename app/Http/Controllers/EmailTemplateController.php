<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AuthorizesSettings;
use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\Language;
use App\Services\LocalizedTemplateRenderer;
use App\Services\AddonManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    use AuthorizesSettings;

    public function __construct(private LocalizedTemplateRenderer $renderer, private AddonManager $addons) {}

    public function index(Request $request)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        $templates = EmailTemplate::query()
            ->where(fn ($query) => $workspace ? $query->whereNull('workspace_id')->orWhere('workspace_id', $workspace->id) : $query)
            ->orderBy('module')->orderBy('name')->get()
            ->filter(fn (EmailTemplate $template) => ! $workspace || $template->module === 'general' || $this->addons->canUse($workspace, $template->module, $request->user()->isSuperAdmin()))
            ->sortByDesc(fn (EmailTemplate $template) => $template->workspace_id ? 1 : 0)
            ->unique(fn (EmailTemplate $template) => $template->module.'|'.$template->name)->values();
        $languages = Language::where('status', true)->get();

        return Inertia::render('Settings/EmailTemplates/Index', [
            'templates' => $templates,
            'languages' => $languages,
        ]);
    }

    public function store(Request $request)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'module' => 'nullable|string|max:100',
            'subject' => 'nullable|string',
            'body' => 'nullable|string',
        ]);

        EmailTemplate::create([
            'name' => $validated['name'],
            'module' => strtolower($validated['module'] ?? 'general'),
            'subject' => $validated['subject'] ?? 'Notification',
            'body' => $validated['body'] ?? '',
            'created_by' => auth()->id(),
            'workspace_id' => $workspace?->id,
        ]);

        return redirect()->back()->with('success', 'Email template created successfully.');
    }

    public function show(EmailTemplate $emailTemplate, Request $request)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        $emailTemplate = $this->localTemplate($emailTemplate, $workspace?->id, $request->user()->id);
        $lang = $request->input('lang', 'en');
        $languages = Language::where('status', true)->get();

        $currTemplate = EmailTemplateLang::firstOrCreate(
            ['parent_id' => $emailTemplate->id, 'lang' => $lang],
            [
                'subject' => $emailTemplate->subject ?? 'Notification',
                'content' => $emailTemplate->body ?? '<p>Hello {name},</p><p>This is an automated notification.</p>',
            ]
        );

        return Inertia::render('Settings/EmailTemplates/Show', [
            'template' => $emailTemplate,
            'currTemplate' => $currTemplate,
            'languages' => $languages,
            'currentLang' => $lang,
        ]);
    }

    public function update(Request $request, EmailTemplate $emailTemplate)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        $emailTemplate = $this->localTemplate($emailTemplate, $workspace?->id, $request->user()->id);
        $validated = $request->validate([
            'lang' => 'required|string',
            'subject' => 'required|string',
            'content' => 'required|string',
            'is_enabled' => 'nullable|boolean',
        ]);

        EmailTemplateLang::updateOrCreate(
            ['parent_id' => $emailTemplate->id, 'lang' => $validated['lang']],
            [
                'subject' => $validated['subject'],
                'content' => $validated['content'],
            ]
        );

        if (array_key_exists('is_enabled', $validated)) {
            $emailTemplate->update(['is_enabled' => $validated['is_enabled']]);
        }

        return redirect()->back()->with('success', 'Email template updated successfully.');
    }

    public function preview(Request $request, EmailTemplate $emailTemplate)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        $emailTemplate = $this->localTemplate($emailTemplate, $workspace?->id, $request->user()->id);
        $validated = $request->validate([
            'lang' => ['nullable', 'string', 'max:10'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['string', 'max:10000'],
        ]);

        return response()->json($this->renderer->email(
            $emailTemplate,
            $validated['lang'] ?? $request->user()->lang ?? 'en',
            $validated['variables'] ?? [],
        ));
    }

    public function reset(Request $request, EmailTemplate $emailTemplate)
    {
        $workspace = $this->settingsWorkspace($request, 'settings.notifications.manage');
        abort_if($workspace && (int) $emailTemplate->workspace_id !== (int) $workspace->id, 404);
        $lang = $request->validate(['lang' => ['required', 'string', 'max:10']])['lang'];
        $emailTemplate->templateLangs()->where('lang', $lang)->delete();

        return back()->with('success', 'Email template language reset to its default.');
    }

    private function localTemplate(EmailTemplate $template, ?int $workspaceId, int $userId): EmailTemplate
    {
        if (! $workspaceId) {
            abort_unless($template->workspace_id === null, 404);
            return $template;
        }
        abort_unless($template->workspace_id === null || (int) $template->workspace_id === $workspaceId, 404);
        if ((int) $template->workspace_id === $workspaceId) {
            return $template;
        }

        return EmailTemplate::firstOrCreate(
            ['workspace_id' => $workspaceId, 'name' => $template->name, 'module' => $template->module],
            ['subject' => $template->subject, 'body' => $template->body, 'variables' => $template->variables, 'is_enabled' => $template->is_enabled, 'created_by' => $userId],
        );
    }
}
