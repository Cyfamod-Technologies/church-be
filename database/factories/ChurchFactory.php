<?php

namespace Database\Factories;

use App\Models\Church;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Church>
 */
class ChurchFactory extends Factory
{
    protected $model = Church::class;

    public function definition(): array
    {
        $name = 'Living Faith '.fake()->city();

        return [
            'name' => $name,
            'code' => 'LFC-'.Str::upper(Str::random(6)),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'district_area' => fake()->citySuffix(),
            'email' => fake()->companyEmail(),
            'phone' => fake()->numerify('+234##########'),
            'pastor_name' => 'Pst. '.fake()->name(),
            'pastor_phone' => fake()->numerify('+234##########'),
            'pastor_email' => fake()->safeEmail(),
            'finance_enabled' => false,
            'special_services_enabled' => true,
            'status' => 'active',
        ];
    }
}
