<?php

namespace Database\Factories;

use App\Domain\Catalog\CatalogTextNormalizer;
use App\Models\Merchant;
use App\Models\NormalizationCandidate;
use App\Models\Receipt;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NormalizationCandidate> */
class NormalizationCandidateFactory extends Factory
{
    public function definition(): array
    {
        $merchant = Merchant::factory()->create();

        return ['user_id' => $merchant->user_id, 'receipt_id' => Receipt::factory()->for($merchant->user), 'entity_type' => 'merchant', 'candidate_merchant_id' => $merchant->id, 'raw_value' => $merchant->display_name, 'normalized_search_key' => app(CatalogTextNormalizer::class)->normalize($merchant->display_name), 'score' => '1.000000', 'normalization_version' => CatalogTextNormalizer::VERSION, 'status' => 'suggested'];
    }
}
