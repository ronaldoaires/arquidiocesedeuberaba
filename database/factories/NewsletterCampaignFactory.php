<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class NewsletterCampaignFactory extends Factory
{
    public function definition(): array
    {
        return ['subject' => fake()->sentence(), 'body_html' => '<p>'.e(fake()->paragraph()).'</p>'];
    }
}
