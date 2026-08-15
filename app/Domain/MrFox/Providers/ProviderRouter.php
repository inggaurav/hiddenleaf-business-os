<?php

namespace App\Domain\MrFox\Providers;

use App\Domain\MrFox\Contracts\AiProviderContract;
use App\Domain\Settings\SettingsManager;
use App\Models\Workspace;

class ProviderRouter
{
    public function __construct(
        private SettingsManager $settingsManager,
        private ?FakeAiProvider $fakeProvider = null
    ) {}

    public function setFakeProvider(FakeAiProvider $fake): void
    {
        $this->fakeProvider = $fake;
    }

    public function resolve(?Workspace $workspace = null): AiProviderContract
    {
        if ($this->fakeProvider !== null) {
            return $this->fakeProvider;
        }

        // If running in automated tests without explicit live keys, use FakeAiProvider
        if (app()->environment('testing')) {
            return new FakeAiProvider();
        }

        $openaiKey = $this->settingsManager->get('openai_api_key', null, $workspace);
        $openaiModel = $this->settingsManager->get('openai_model', 'gpt-4o', $workspace) ?: 'gpt-4o';
        $geminiKey = $this->settingsManager->get('gemini_api_key', null, $workspace);
        $preferred = $this->settingsManager->get('ai_provider', 'openai', $workspace);

        if ($preferred === 'gemini' && ! empty($geminiKey)) {
            return new GeminiProvider($geminiKey, 'gemini-1.5-pro');
        }

        if (! empty($openaiKey)) {
            return new OpenAiProvider($openaiKey, $openaiModel);
        }

        if (! empty($geminiKey)) {
            return new GeminiProvider($geminiKey, 'gemini-1.5-pro');
        }

        // Fallback default
        return new OpenAiProvider(null, 'gpt-4o');
    }
}
