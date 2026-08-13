<?php

namespace App\Http\Controllers\SuperAdmin;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\File;

class TranslationController
{
    public function index()
    {
        $locales = ['en', 'es', 'fr']; // simplified list
        $translations = [];

        foreach ($locales as $locale) {
            $path = base_path("lang/{$locale}.json");
            if (File::exists($path)) {
                $translations[$locale] = json_decode(File::get($path), true);
            } else {
                $translations[$locale] = [];
            }
        }

        return Inertia::render('SuperAdmin/Translations/Index', [
            'translations' => $translations,
            'locales' => $locales,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'locale' => 'required|string',
            'key' => 'required|string',
            'value' => 'required|string',
        ]);

        $locale = $request->locale;
        $path = base_path("lang/{$locale}.json");
        
        $translations = [];
        if (File::exists($path)) {
            $translations = json_decode(File::get($path), true) ?? [];
        }

        $translations[$request->key] = $request->value;
        
        File::put($path, json_encode($translations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        return redirect()->back()->with('success', 'Translation updated successfully.');
    }
}
