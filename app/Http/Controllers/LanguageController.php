<?php

namespace App\Http\Controllers;

use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class LanguageController extends Controller
{
    public function index()
    {
        $languages = Language::all();
        $defaultLang = admin_setting('default_language', 'en');

        return Inertia::render('Languages/Index', [
            'languages' => $languages,
            'defaultLang' => $defaultLang,
        ]);
    }

    public function create()
    {
        return Inertia::render('Languages/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:languages,code',
            'name' => 'required|string|max:100',
        ]);

        Language::create([
            'code' => strtolower($validated['code']),
            'name' => $validated['name'],
            'status' => true,
        ]);

        $langDir = resource_path("lang/{$validated['code']}");
        if (! File::exists($langDir)) {
            File::makeDirectory($langDir, 0755, true);
        }

        return redirect()->route('languages.index')->with('success', 'Language created successfully.');
    }

    public function changeLang(Request $request, string $lang)
    {
        if (Language::where('code', $lang)->where('status', true)->exists() || $lang === 'en') {
            session(['locale' => $lang]);
            App::setLocale($lang);
        }

        return redirect()->back()->with('success', 'Language switched successfully.');
    }

    public function edit(Language $language)
    {
        return $this->show($language->code);
    }

    public function update(Request $request, Language $language)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'status' => 'nullable|boolean',
        ]);

        $language->update($validated);

        return redirect()->route('languages.index')->with('success', 'Language updated successfully.');
    }

    public function show(string $lang)
    {
        $language = Language::where('code', $lang)->first();
        $langDir = resource_path("lang/{$lang}");
        $translations = [];

        if (File::exists("{$langDir}.json")) {
            $translations = json_decode(File::get("{$langDir}.json"), true) ?? [];
        }

        return Inertia::render('Languages/Show', [
            'language' => $language,
            'langCode' => $lang,
            'translations' => $translations,
        ]);
    }

    public function saveLanguageData(Request $request, string $lang)
    {
        $validated = $request->validate([
            'translations' => 'required|array',
        ]);

        $jsonPath = resource_path("lang/{$lang}.json");
        $langDir = resource_path('lang');
        if (! File::exists($langDir)) {
            File::makeDirectory($langDir, 0755, true);
        }

        File::put($jsonPath, json_encode($validated['translations'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return redirect()->back()->with('success', 'Translations saved successfully.');
    }

    public function destroy(Language $language)
    {
        if ($language->code === 'en') {
            return redirect()->back()->with('error', 'Cannot delete default English language.');
        }

        $language->delete();

        return redirect()->route('languages.index')->with('success', 'Language deleted successfully.');
    }
}
