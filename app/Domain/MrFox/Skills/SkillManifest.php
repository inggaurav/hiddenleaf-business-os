<?php

namespace App\Domain\MrFox\Skills;

class SkillManifest
{
    public function __construct(
        public string $id,
        public string $name,
        public string $category,
        public string $description,
        public array $inputSchema,
        public array $outputSchema,
        public string $promptTemplate
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'description' => $this->description,
            'input_schema' => $this->inputSchema,
            'output_schema' => $this->outputSchema,
        ];
    }
}
