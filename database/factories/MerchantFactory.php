<?php

namespace Database\Factories;

use App\Domain\Catalog\CatalogTextNormalizer;
use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Merchant> */
class MerchantFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return ['user_id' => User::factory(), 'display_name' => $name, 'normalized_search_key' => app(CatalogTextNormalizer::class)->normalize($name), 'is_active' => true, 'normalization_version' => CatalogTextNormalizer::VERSION, 'version' => 1];
    }
}
