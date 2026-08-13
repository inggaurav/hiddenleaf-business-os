<?php

namespace Database\Factories;

use App\Models\Workspace;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class WorkspaceFactory extends Factory
{
    protected $model = Workspace::class;

    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'name' => 'Operations ' . rand(1, 100),
            'slug' => 'operations-' . rand(1, 100),
            'created_by' => User::factory(),
            'is_active' => true,
        ];
    }
}
