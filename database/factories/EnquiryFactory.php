<?php

namespace Database\Factories;

use App\Enums\EnquiryStatus;
use App\Enums\Priority;
use App\Models\Enquiry;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enquiry>
 */
class EnquiryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_name' => fake()->name(),
            'student_email' => fake()->safeEmail(),
            'subject' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'status' => EnquiryStatus::New,
            'priority' => fake()->randomElement(Priority::cases()),
            'version' => 1,
        ];
    }

    public function status(EnquiryStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
        ]);
    }

    public function priority(Priority $priority): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $priority,
        ]);
    }
}
