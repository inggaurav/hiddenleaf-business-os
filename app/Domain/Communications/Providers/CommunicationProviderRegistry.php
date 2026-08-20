<?php

namespace App\Domain\Communications\Providers;

use App\Domain\Communications\Contracts\CommunicationProviderContract;
use InvalidArgumentException;

class CommunicationProviderRegistry
{
    /** @var array<string, CommunicationProviderContract> */
    private array $providers = [];

    public function __construct()
    {
        $this->register(new GmailProvider);
        $this->register(new WhatsAppCloudProvider);
        $this->register(new SlackProvider);
        $this->register(new MetaSocialProvider('facebook'));
        $this->register(new MetaSocialProvider('instagram'));
        $this->register(new InternalMessengerProvider);
    }

    public function register(CommunicationProviderContract $provider): void
    {
        $this->providers[$provider->name()] = $provider;
    }

    public function get(string $name): CommunicationProviderContract
    {
        $normalized = strtolower(trim($name));

        if (! isset($this->providers[$normalized])) {
            throw new InvalidArgumentException("Communication provider '{$name}' is not registered.");
        }

        return $this->providers[$normalized];
    }

    public function has(string $name): bool
    {
        return isset($this->providers[strtolower(trim($name))]);
    }

    public function all(): array
    {
        return $this->providers;
    }
}
