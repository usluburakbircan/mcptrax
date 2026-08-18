<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<\App\Models\Monitor>
 */
class MonitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->words(2, true),
            'url' => 'https://example.test/mcp',
            'interval_seconds' => 900,
            'slug' => Str::lower(Str::random(10)),
        ];
    }
}
