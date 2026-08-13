<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingSite extends Model
{
    protected $fillable = ['organization_id', 'workspace_id', 'name', 'slug', 'title', 'description', 'logo_path', 'seo_title', 'seo_description', 'seo_keywords', 'is_published', 'locale'];

    protected function casts(): array
    {
        return ['seo_keywords' => 'array', 'is_published' => 'boolean'];
    }

    public function sections()
    {
        return $this->hasMany(LandingSection::class, 'site_id')->orderBy('position');
    }

    public function pages()
    {
        return $this->hasMany(LandingPage::class, 'site_id')->orderBy('position');
    }
}
