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

        return Inertia::render('Landing/Manage', [
            'sites' => LandingSite::where('organization_id', $w->organization_id)->where('workspace_id', $w->id)->with(['sections', 'pages'])->get(),
            'marketplaceItems' => \DB::table('landing_marketplace_items')->where('workspace_id', $w->id)->orderBy('position')->get(),
            'subscribers' => \DB::table('landing_newsletter_subscribers')->where('workspace_id', $w->id)->latest('subscribed_at')->get(),
        ]);
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

    public function destroySite(Request $r, LandingSite $site)
    {
        $w = $this->workspace($r);
        $this->tenant($site, $w);
        $site->delete();

        return back()->with('success', 'Landing site deleted.');
    }

    public function destroySection(Request $r, LandingSite $site, LandingSection $section)
    {
        $w = $this->workspace($r);
        $this->tenant($site, $w);
        abort_unless((int) $section->site_id === (int) $site->id, 404);
        $section->delete();

        return back()->with('success', 'Landing section deleted.');
    }

    public function destroyPage(Request $r, LandingSite $site, LandingPage $page)
    {
        $w = $this->workspace($r);
        $this->tenant($site, $w);
        abort_unless((int) $page->site_id === (int) $site->id, 404);
        $page->delete();

        return back()->with('success', 'Custom page deleted.');
    }

    public function storeMarketplaceItem(Request $r)
    {
        $w = $this->workspace($r);
        $d = $r->validate(['name' => ['required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'url' => ['nullable', 'url'], 'position' => ['nullable', 'integer', 'min:0'], 'is_visible' => ['boolean']]);
        \DB::table('landing_marketplace_items')->insert($d + ['organization_id' => $w->organization_id, 'workspace_id' => $w->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Marketplace item saved.');
    }

    public function destroyMarketplaceItem(Request $r, int $item)
    {
        $w = $this->workspace($r);
        abort_unless(\DB::table('landing_marketplace_items')->where('workspace_id', $w->id)->where('id', $item)->delete(), 404);

        return back()->with('success', 'Marketplace item deleted.');
    }

    public function storeSubscriber(Request $r)
    {
        $w = $this->workspace($r);
        $d = $r->validate(['email' => ['required', 'email', 'max:255'], 'name' => ['nullable', 'string', 'max:255']]);
        \DB::table('landing_newsletter_subscribers')->updateOrInsert(['workspace_id' => $w->id, 'email' => $d['email']], $d + ['organization_id' => $w->organization_id, 'status' => 'subscribed', 'subscribed_at' => now(), 'unsubscribed_at' => null, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'Newsletter subscriber saved.');
    }

    public function unsubscribe(Request $r, int $subscriber)
    {
        $w = $this->workspace($r);
        abort_unless(\DB::table('landing_newsletter_subscribers')->where('workspace_id', $w->id)->where('id', $subscriber)->update(['status' => 'unsubscribed', 'unsubscribed_at' => now(), 'updated_at' => now()]), 404);

        return back()->with('success', 'Subscriber unsubscribed.');
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
