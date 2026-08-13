<?php

namespace App\Http\Controllers;

use App\Models\Language;
use App\Models\NotificationTemplate;
use App\Models\NotificationTemplateLang;
use App\Services\LocalizedTemplateRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class NotificationTemplateController extends Controller
{
    public function __construct(private LocalizedTemplateRenderer $renderer) {}

    public function index()
    {
        $templates = NotificationTemplate::all();
        $languages = Language::where('status', true)->get();

        return Inertia::render('Settings/NotificationTemplates/Index', [
            'templates' => $templates,
            'languages' => $languages,
        ]);
    }

    public function show(NotificationTemplate $notificationTemplate, Request $request)
    {
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
        $validated = $request->validate([
            'lang' => 'required|string',
            'content' => 'required|string',
        ]);

        NotificationTemplateLang::updateOrCreate(
            ['parent_id' => $notificationTemplate->id, 'lang' => $validated['lang']],
            [
                'content' => $validated['content'],
            ]
        );

        return redirect()->back()->with('success', 'Notification template updated successfully.');
    }

    public function preview(Request $request, NotificationTemplate $notificationTemplate)
    {
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
}
