<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;

class TranslationController extends Controller
{
    public function index(Request $request)
    {
        $languages = Language::all();
        $locales = $languages->pluck('code')->all();
        if (! in_array('en', $locales, true)) {
            array_unshift($locales, 'en');
        }

        $translations = [];
        foreach ($locales as $loc) {
            $translations[$loc] = [];
            $resourceJson = resource_path("lang/{$loc}.json");
            $baseJson = base_path("lang/{$loc}.json");

            if (File::exists($resourceJson)) {
                $decoded = json_decode(File::get($resourceJson), true);
                if (is_array($decoded)) {
                    $translations[$loc] = array_merge($translations[$loc], $decoded);
                }
            } elseif (File::exists($baseJson)) {
                $decoded = json_decode(File::get($baseJson), true);
                if (is_array($decoded)) {
                    $translations[$loc] = array_merge($translations[$loc], $decoded);
                }
            }
        }

        return Inertia::render('SuperAdmin/Translations/Index', [
            'translations' => $translations,
            'locales' => array_values(array_unique($locales)),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'locale' => 'required|string|max:10',
            'key'    => 'required|string|max:255',
            'value'  => 'required|string|max:2000',
        ]);

        $locale = strtolower($validated['locale']);
        $key = $validated['key'];
        $value = $validated['value'];

        $langDir = resource_path('lang');
        if (! File::exists($langDir)) {
            File::makeDirectory($langDir, 0755, true);
        }

        $jsonPath = resource_path("lang/{$locale}.json");
        $existing = [];
        if (File::exists($jsonPath)) {
            $existing = json_decode(File::get($jsonPath), true) ?? [];
        }

        $existing[$key] = $value;
        File::put($jsonPath, json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        Language::firstOrCreate(
            ['code' => $locale],
            ['name' => strtoupper($locale), 'status' => true]
        );

        return redirect()->back()->with('success', "Translation for [{$key}] updated successfully.");
    }
}
