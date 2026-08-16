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

        if (app()->environment('testing')) {
            return new FakeAiProvider;
        }

        $preferred = $this->settingsManager->get('ai_provider', 'openai', $workspace);
        $openaiKey = $this->settingsManager->get('openai_api_key', null, $workspace);
        $openaiModel = $this->settingsManager->get('openai_model', 'gpt-4o', $workspace) ?: 'gpt-4o';
        $geminiKey = $this->settingsManager->get('gemini_api_key', null, $workspace);
        $anthropicKey = $this->settingsManager->get('anthropic_api_key', null, $workspace);
        $anthropicModel = $this->settingsManager->get('anthropic_model', 'claude-3-5-sonnet-20241022', $workspace);
        $groqKey = $this->settingsManager->get('groq_api_key', null, $workspace);
        $ollamaHost = $this->settingsManager->get('ollama_host', 'http://localhost:11434', $workspace);
        $ollamaModel = $this->settingsManager->get('ollama_model', 'llama3.2', $workspace);

        $allowFallback = (bool) $this->settingsManager->get('allow_provider_fallback', false, $workspace);

        switch ($preferred) {
            case 'anthropic':
                if (! empty($anthropicKey)) {
                    return new AnthropicProvider($anthropicKey, $anthropicModel);
                }
                break;
            case 'gemini':
                if (! empty($geminiKey)) {
                    return new GeminiProvider($geminiKey, 'gemini-1.5-pro');
                }
                break;
            case 'groq':
                if (! empty($groqKey)) {
                    return new GroqProvider($groqKey);
                }
                break;
            case 'ollama':
                return new OllamaProvider($ollamaHost, $ollamaModel);
            case 'openai':
            default:
                if (! empty($openaiKey)) {
                    return new OpenAiProvider($openaiKey, $openaiModel);
                }
                break;
        }

        // Controlled fallback if allowed
        if ($allowFallback) {
            if (! empty($openaiKey)) {
                return new OpenAiProvider($openaiKey, $openaiModel);
            }
            if (! empty($anthropicKey)) {
                return new AnthropicProvider($anthropicKey, $anthropicModel);
            }
            if (! empty($geminiKey)) {
                return new GeminiProvider($geminiKey, 'gemini-1.5-pro');
            }
            if (! empty($groqKey)) {
                return new GroqProvider($groqKey);
            }
        }

        // Return preferred with empty keys for clear error message
        return match ($preferred) {
            'anthropic' => new AnthropicProvider(null, $anthropicModel),
            'gemini' => new GeminiProvider(null, 'gemini-1.5-pro'),
            'groq' => new GroqProvider(null),
            'ollama' => new OllamaProvider($ollamaHost, $ollamaModel),
            default => new OpenAiProvider(null, $openaiModel),
        };
    }
}
