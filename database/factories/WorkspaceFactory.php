<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Operations '.fake()->unique()->numberBetween(1, 1_000_000),
            'slug' => 'operations-'.Str::lower((string) Str::ulid()),
            'created_by' => User::factory(),
            'is_active' => true,
        ];
    }
}
