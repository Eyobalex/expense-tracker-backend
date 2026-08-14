<?php

namespace Database\Factories;

use App\Models\FinancialTransaction;
use App\Models\TransactionDuplicateCandidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionDuplicateCandidate>
 */
class TransactionDuplicateCandidateFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'financial_transaction_id' => FinancialTransaction::factory(),
            'candidate_transaction_id' => FinancialTransaction::factory(),
            'score' => '0.750000',
            'score_breakdown' => ['amount' => 0.3, 'currency' => 0.1, 'date_proximity' => 0.2],
            'algorithm_version' => 'duplicate-score-v1',
            'status' => 'suggested',
        ];
    }
}
