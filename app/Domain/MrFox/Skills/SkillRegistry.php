<?php

namespace App\Domain\MrFox\Skills;

class SkillRegistry
{
    /** @var array<string, SkillManifest> */
    private array $skills = [];

    public function __construct()
    {
        $this->registerCoreSkills();
    }

    public function register(SkillManifest $manifest): void
    {
        $this->skills[$manifest->id] = $manifest;
    }

    public function get(string $id): ?SkillManifest
    {
        return $this->skills[$id] ?? null;
    }

    public function all(): array
    {
        return $this->skills;
    }

    private function registerCoreSkills(): void
    {
        $this->register(new SkillManifest(
            id: 'social_post',
            name: 'Social Media Post Generator',
            category: 'marketing',
            description: 'Generate high-converting social media posts with platform-specific formatting, hashtags, and CTA.',
            inputSchema: [
                'type' => 'object',
                'required' => ['platform', 'topic'],
                'properties' => [
                    'platform' => ['type' => 'string', 'description' => 'Target platform: linkedin, x_twitter, instagram, facebook'],
                    'topic' => ['type' => 'string', 'description' => 'Core topic, announcement, or theme of the post'],
                    'tone' => ['type' => 'string', 'description' => 'Optional tone modifier'],
                ],
            ],
            outputSchema: ['type' => 'object', 'properties' => ['caption' => ['type' => 'string'], 'hashtags' => ['type' => 'array'], 'cta' => ['type' => 'string']]],
            promptTemplate: 'Write a high-converting {platform} post about {topic}. Apply {tone} tone and brand voice guidelines.'
        ));

        $this->register(new SkillManifest(
            id: 'meta_ad',
            name: 'Meta Ad Copy Generator',
            category: 'advertising',
            description: 'Generate Facebook & Instagram ad creative copy with primary text, punchy headlines, and descriptions.',
            inputSchema: [
                'type' => 'object',
                'required' => ['product', 'offer'],
                'properties' => [
                    'product' => ['type' => 'string', 'description' => 'Product or service being advertised'],
                    'offer' => ['type' => 'string', 'description' => 'The special incentive, discount, or unique value offer'],
                ],
            ],
            outputSchema: ['type' => 'object', 'properties' => ['primary_text' => ['type' => 'string'], 'headline' => ['type' => 'string'], 'description' => ['type' => 'string']]],
            promptTemplate: 'Create a direct-response Meta ad for {product} highlighting offer {offer}.'
        ));

        $this->register(new SkillManifest(
            id: 'seo_blog',
            name: 'SEO Blog Article Generator',
            category: 'content',
            description: 'Generate structured SEO blog outlines and articles with optimized headers, meta descriptions, and keywords.',
            inputSchema: [
                'type' => 'object',
                'required' => ['topic', 'primary_keyword'],
                'properties' => [
                    'topic' => ['type' => 'string', 'description' => 'Article title or topic'],
                    'primary_keyword' => ['type' => 'string', 'description' => 'Target search query for SEO ranking'],
                ],
            ],
            outputSchema: ['type' => 'object', 'properties' => ['meta_title' => ['type' => 'string'], 'meta_description' => ['type' => 'string'], 'content' => ['type' => 'string']]],
            promptTemplate: 'Generate an authoritative, comprehensive SEO blog post on {topic} targeting keyword {primary_keyword}.'
        ));

        $this->register(new SkillManifest(
            id: 'email_campaign',
            name: 'Email Campaign Copywriter',
            category: 'email',
            description: 'Write engaging email newsletters, promotional sequences, or customer onboarding emails with subject lines.',
            inputSchema: [
                'type' => 'object',
                'required' => ['campaign_type', 'subject_topic'],
                'properties' => [
                    'campaign_type' => ['type' => 'string', 'description' => 'Type: newsletter, promotional, onboarding, re_engagement'],
                    'subject_topic' => ['type' => 'string', 'description' => 'Subject matter and key call to action'],
                ],
            ],
            outputSchema: ['type' => 'object', 'properties' => ['subject_lines' => ['type' => 'array'], 'preview_text' => ['type' => 'string'], 'body' => ['type' => 'string']]],
            promptTemplate: 'Write an effective {campaign_type} email about {subject_topic} with 3 compelling subject lines.'
        ));

        $this->register(new SkillManifest(
            id: 'repurpose_content',
            name: 'Content Repurposing Engine',
            category: 'content',
            description: 'Repurpose existing long-form articles, case studies, or documents into bite-sized social snippets and key takeaways.',
            inputSchema: [
                'type' => 'object',
                'required' => ['source_text', 'target_format'],
                'properties' => [
                    'source_text' => ['type' => 'string', 'description' => 'The original text content'],
                    'target_format' => ['type' => 'string', 'description' => 'Format: twitter_thread, linkedin_bullets, summary_quotes'],
                ],
            ],
            outputSchema: ['type' => 'object', 'properties' => ['repurposed_content' => ['type' => 'string']]],
            promptTemplate: 'Repurpose the following content into a {target_format}:\n\n{source_text}'
        ));
    }
}
