<?php

namespace HiddenLeaf\Kernel\Contracts;

interface AddonExtensionContract
{
    /** @return array<int, class-string> */
    public function mrFoxTools(): array;

    /** @return array<int, class-string> */
    public function automationTriggers(): array;

    /** @return array<int, class-string> */
    public function automationActions(): array;

    /** @return array<int, class-string> */
    public function searchProviders(): array;

    /** @return array<int, class-string> */
    public function commandCenterSignals(): array;
}
