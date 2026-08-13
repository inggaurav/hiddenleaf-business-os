<?php

namespace HiddenLeaf\Kernel\Contracts;

interface AddonContract
{
    public function getId(): string;
    public function getTitle(): string;
    public function getVersion(): string;
    public function getRequiredCoreVersion(): string;
    public function register(): void;
    public function boot(): void;
}
