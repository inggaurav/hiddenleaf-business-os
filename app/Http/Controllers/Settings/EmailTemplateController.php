<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\EmailTemplate;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmailTemplateController
{
    public function index()
    {
        return Inertia::render('Settings/EmailTemplates', [
            'templates' => EmailTemplate::all()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
        ]);
        
        EmailTemplate::create($validated);
        
        return redirect()->back();
    }
}
