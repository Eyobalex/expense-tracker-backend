<?php

namespace Database\Factories;

use App\Domain\Catalog\CatalogTextNormalizer;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Item> */
class ItemFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->lexify('item-??????');

        return ['user_id' => User::factory(), 'canonical_name' => $name, 'normalized_search_key' => app(CatalogTextNormalizer::class)->normalize($name), 'is_active' => true, 'normalization_version' => CatalogTextNormalizer::VERSION, 'version' => 1];
    }
}
