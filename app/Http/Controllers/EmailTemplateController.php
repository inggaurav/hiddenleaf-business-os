<?php

namespace App\Http\Controllers;

use App\Models\EmailTemplate;
use App\Models\EmailTemplateLang;
use App\Models\Language;
use App\Services\LocalizedTemplateRenderer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailTemplateController extends Controller
{
    public function __construct(private LocalizedTemplateRenderer $renderer) {}

    public function index()
    {
        $templates = EmailTemplate::all();
        $languages = Language::where('status', true)->get();

        return Inertia::render('Settings/EmailTemplates/Index', [
            'templates' => $templates,
            'languages' => $languages,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'nullable|string',
            'body' => 'nullable|string',
        ]);

        EmailTemplate::create([
            'name' => $validated['name'],
            'subject' => $validated['subject'] ?? 'Notification',
            'body' => $validated['body'] ?? '',
            'created_by' => auth()->id(),
        ]);

        return redirect()->back()->with('success', 'Email template created successfully.');
    }

    public function show(EmailTemplate $emailTemplate, Request $request)
    {
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
        $validated = $request->validate([
            'lang' => 'required|string',
            'subject' => 'required|string',
            'content' => 'required|string',
        ]);

        EmailTemplateLang::updateOrCreate(
            ['parent_id' => $emailTemplate->id, 'lang' => $validated['lang']],
            [
                'subject' => $validated['subject'],
                'content' => $validated['content'],
            ]
        );

        return redirect()->back()->with('success', 'Email template updated successfully.');
    }

    public function preview(Request $request, EmailTemplate $emailTemplate)
    {
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
}
