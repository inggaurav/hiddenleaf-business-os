<?php

namespace App\Http\Controllers;

use App\Models\LandingPage;
use App\Models\LandingSection;
use App\Models\LandingSite;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class LandingPageController extends Controller
{
    public function manage(Request $r)
    {
        $w = $this->workspace($r);

        return Inertia::render('Landing/Manage', ['sites' => LandingSite::where('organization_id', $w->organization_id)->where('workspace_id', $w->id)->with(['sections', 'pages'])->get()]);
    }

    public function storeSite(Request $r)
    {
        $w = $this->workspace($r);
        $d = $r->validate(['name' => ['required', 'string'], 'slug' => ['required', 'alpha_dash', Rule::unique('landing_sites')], 'title' => ['required', 'string'], 'description' => ['nullable', 'string'], 'seo_title' => ['nullable', 'string'], 'seo_description' => ['nullable', 'string'], 'seo_keywords' => ['array'], 'locale' => ['required', 'string', 'max:10']]);
        LandingSite::create($d + ['organization_id' => $w->organization_id, 'workspace_id' => $w->id, 'is_published' => false]);

        return back()->with('success', 'Landing site created.');
    }

    public function section(Request $r, LandingSite $site)
    {
        $w = $this->workspace($r);
        $this->tenant($site, $w);
        $d = $r->validate(['type' => ['required', Rule::in(['hero', 'features', 'pricing', 'testimonials', 'faq', 'cta', 'custom'])], 'heading' => ['nullable', 'string'], 'subheading' => ['nullable', 'string'], 'content' => ['nullable', 'array'], 'position' => ['required', 'integer', 'min:0'], 'is_visible' => ['boolean']]);
        LandingSection::updateOrCreate(['site_id' => $site->id, 'type' => $d['type'], 'position' => $d['position']], $d);

        return back()->with('success', 'Landing section saved.');
    }

    public function page(Request $r, LandingSite $site)
    {
        $w = $this->workspace($r);
        $this->tenant($site, $w);
        $d = $r->validate(['slug' => ['required', 'alpha_dash'], 'title' => ['required', 'string'], 'content' => ['required', 'string'], 'seo_title' => ['nullable', 'string'], 'seo_description' => ['nullable', 'string'], 'is_published' => ['boolean'], 'position' => ['integer', 'min:0']]);
        LandingPage::updateOrCreate(['site_id' => $site->id, 'slug' => $d['slug']], $d);

        return back()->with('success', 'Custom page saved.');
    }

    public function publish(Request $r, LandingSite $site)
    {
        $w = $this->workspace($r);
        $this->tenant($site, $w);
        $d = $r->validate(['published' => ['required', 'boolean']]);
        $site->update(['is_published' => $d['published']]);

        return back()->with('success', 'Publishing status updated.');
    }

    public function publicSite(string $slug)
    {
        $site = LandingSite::where('slug', $slug)->where('is_published', true)->with(['sections' => fn ($q) => $q->where('is_visible', true), 'pages' => fn ($q) => $q->where('is_published', true)])->firstOrFail();

        return Inertia::render('Landing/Public', ['site' => $site]);
    }

    public function publicPage(string $slug, string $page)
    {
        $site = LandingSite::where('slug', $slug)->where('is_published', true)->firstOrFail();
        $record = $site->pages()->where('slug', $page)->where('is_published', true)->firstOrFail();

        return Inertia::render('Landing/Page', ['site' => $site, 'page' => $record]);
    }

    private function workspace(Request $r): Workspace
    {
        $w = Workspace::with('organization')->find($r->session()->get('active_workspace_id'));
        abort_unless($w && $r->user()->canInWorkspace('landing.manage', $w), 403);

        return $w;
    }

    private function tenant(LandingSite $s, Workspace $w): void
    {
        abort_unless((int) $s->organization_id === (int) $w->organization_id && (int) $s->workspace_id === (int) $w->id, 404);
    }
}
