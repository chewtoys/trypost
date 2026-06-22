<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\Plan\Slug;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => Slug::Workspace,
            'name' => 'Workspace',
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
            'monthly_credits_limit' => 2500,
            'sort' => 0,
            'is_archived' => false,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_archived' => true,
        ]);
    }
}
