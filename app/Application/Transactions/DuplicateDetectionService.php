<?php

namespace App\Application\Transactions;

use App\Domain\Shared\Exceptions\DomainErrorCode;
use App\Domain\Shared\Exceptions\DomainException;
use App\Models\AuditEvent;
use App\Models\FinancialTransaction;
use App\Models\TransactionDuplicateCandidate;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final readonly class DuplicateDetectionService
{
    public const string AlgorithmVersion = 'duplicate-score-v1';

    private const float MinimumScore = 0.600000;

    /**
     * @return Collection<int, TransactionDuplicateCandidate>
     */
    public function discover(User $user, FinancialTransaction $transaction): Collection
    {
        if ($transaction->user_id !== $user->id || in_array($transaction->state, ['cancelled', 'reversed'], true)) {
            return new Collection;
        }

        $candidates = FinancialTransaction::query()
            ->ownedBy($user)
            ->whereKeyNot($transaction->getKey())
            ->whereNotIn('state', ['cancelled', 'reversed'])
            ->where(function ($query) use ($transaction): void {
                $query->whereBetween('occurred_at', [$transaction->occurred_at->subDays(7), $transaction->occurred_at->addDays(7)])
                    ->orWhere('original_amount_minor_units', $transaction->original_amount_minor_units);

                if ($transaction->reference_number !== null && $transaction->reference_number !== '') {
                    $query->orWhere('reference_number', $transaction->reference_number);
                }
            })
            ->with('reviewReceipt')
            ->orderByDesc('occurred_at')
            ->limit(200)
            ->get();

        $sourceChecksum = $transaction->relationLoaded('reviewReceipt')
            ? $transaction->reviewReceipt?->checksum_sha256
            : $transaction->reviewReceipt()->value('checksum_sha256');

        foreach ($candidates as $candidateTransaction) {
            $breakdown = $this->score($transaction, $candidateTransaction, $sourceChecksum);
            if ($breakdown['total'] < self::MinimumScore) {
                continue;
            }

            TransactionDuplicateCandidate::query()->firstOrCreate(
                [
                    'financial_transaction_id' => $transaction->getKey(),
                    'candidate_transaction_id' => $candidateTransaction->getKey(),
                ],
                [
                    'user_id' => $user->getKey(),
                    'score' => number_format($breakdown['total'], 6, '.', ''),
                    'score_breakdown' => $breakdown,
                    'algorithm_version' => self::AlgorithmVersion,
                    'status' => 'suggested',
                ],
            );
        }

        return TransactionDuplicateCandidate::query()
            ->where('user_id', $user->getKey())
            ->where('financial_transaction_id', $transaction->getKey())
            ->with('candidateTransaction')
            ->orderByDesc('score')
            ->orderBy('id')
            ->get();
    }

    public function resolve(User $user, FinancialTransaction $transaction, string $candidateId, string $decision): TransactionDuplicateCandidate
    {
        return DB::transaction(function () use ($user, $transaction, $candidateId, $decision): TransactionDuplicateCandidate {
            /** @var FinancialTransaction|null $source */
            $source = FinancialTransaction::query()->ownedBy($user)->whereKey($transaction->getKey())->lockForUpdate()->first();
            if (! $source instanceof FinancialTransaction || ! in_array($source->state, ['draft', 'pending_review'], true)) {
                throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Only an unposted transaction can resolve duplicate candidates.');
            }

            /** @var TransactionDuplicateCandidate|null $candidate */
            $candidate = TransactionDuplicateCandidate::query()
                ->where('user_id', $user->getKey())
                ->where('financial_transaction_id', $source->getKey())
                ->whereKey($candidateId)
                ->lockForUpdate()
                ->first();
            if (! $candidate instanceof TransactionDuplicateCandidate) {
                throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The duplicate candidate was not found.');
            }

            /** @var FinancialTransaction|null $existing */
            $existing = FinancialTransaction::query()->ownedBy($user)->whereKey($candidate->candidate_transaction_id)->lockForUpdate()->first();
            if (! $existing instanceof FinancialTransaction) {
                throw DomainException::for(DomainErrorCode::ResourceNotFound, 'The candidate transaction was not found.');
            }

            $now = now();
            if ($decision === 'replace_pending_draft') {
                if (! in_array($existing->state, ['draft', 'pending_review'], true)) {
                    throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Only an unposted candidate transaction can be replaced.');
                }
                $existing->forceFill([
                    'state' => 'cancelled',
                    'cancelled_at' => $now,
                    'duplicate_replaced_by_id' => $source->getKey(),
                    'version' => $existing->version + 1,
                ])->save();
            }

            if ($decision === 'cancel_current_import') {
                $source->forceFill([
                    'state' => 'cancelled',
                    'cancelled_at' => $now,
                    'version' => $source->version + 1,
                ])->save();
            }

            $candidate->forceFill([
                'status' => match ($decision) {
                    'view_existing' => 'viewed',
                    'keep_both' => 'keep_both',
                    'replace_pending_draft' => 'replaced',
                    'cancel_current_import' => 'cancelled',
                    default => throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'The duplicate decision is not supported.'),
                },
                'decided_by_user_id' => $user->getKey(),
                'decided_at' => $now,
            ])->save();

            AuditEvent::query()->create([
                'actor_user_id' => $user->getKey(),
                'user_id' => $user->getKey(),
                'event_name' => 'transaction.duplicate_decision_recorded',
                'aggregate_type' => 'financial_transaction',
                'aggregate_id' => $source->getKey(),
                'summary' => [
                    'decision' => $decision,
                    'candidate_id' => $candidate->getKey(),
                    'source_transaction_id' => $source->getKey(),
                    'candidate_transaction_id' => $existing->getKey(),
                ],
            ]);

            return $candidate->fresh(['candidateTransaction']);
        }, attempts: 3);
    }

    public function assertNoUnresolvedCandidates(User $user, FinancialTransaction $transaction): void
    {
        $this->discover($user, $transaction);
        $unresolved = TransactionDuplicateCandidate::query()
            ->where('user_id', $user->getKey())
            ->where('financial_transaction_id', $transaction->getKey())
            ->whereIn('status', ['suggested', 'viewed'])
            ->exists();
        if ($unresolved) {
            throw DomainException::for(DomainErrorCode::InvalidStateTransition, 'Resolve duplicate candidates before posting this transaction.');
        }
    }

    /**
     * @return array{amount: float, currency: float, date_proximity: float, account: float, merchant: float, reference_number: float, source_checksum: float, total: float}
     */
    private function score(FinancialTransaction $source, FinancialTransaction $candidate, ?string $sourceChecksum): array
    {
        $seconds = abs($source->occurred_at->diffInSeconds($candidate->occurred_at));
        $dateProximity = $seconds <= 300 ? 0.2 : ($seconds <= 86400 ? 0.15 : ($seconds <= 259200 ? 0.1 : 0.0));
        $merchantMatches = $source->merchant_id !== null && $source->merchant_id === $candidate->merchant_id;
        $merchantMatches = $merchantMatches || ($source->raw_merchant_text !== null && $source->raw_merchant_text !== '' && mb_strtolower(trim($source->raw_merchant_text)) === mb_strtolower(trim((string) $candidate->raw_merchant_text)));
        $referenceMatches = $source->reference_number !== null && $source->reference_number !== '' && $source->reference_number === $candidate->reference_number;
        $checksumMatches = $sourceChecksum !== null && $sourceChecksum === $candidate->reviewReceipt?->checksum_sha256;
        $breakdown = [
            'amount' => $source->original_amount_minor_units === $candidate->original_amount_minor_units ? 0.3 : 0.0,
            'currency' => $source->original_currency_code === $candidate->original_currency_code ? 0.1 : 0.0,
            'date_proximity' => $dateProximity,
            'account' => $source->financial_account_id === $candidate->financial_account_id ? 0.15 : 0.0,
            'merchant' => $merchantMatches ? 0.1 : 0.0,
            'reference_number' => $referenceMatches ? 0.6 : 0.0,
            'source_checksum' => $checksumMatches ? 0.6 : 0.0,
        ];
        $breakdown['total'] = min(1.0, array_sum($breakdown));

        return $breakdown;
    }
}
