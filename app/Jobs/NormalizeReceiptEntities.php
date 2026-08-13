<?php

namespace App\Jobs;

use App\Application\Catalog\CatalogService;
use App\Models\Receipt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NormalizeReceiptEntities implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public string $receiptId) {}

    public function handle(CatalogService $catalog): void
    {
        $receipt = Receipt::query()->with('extractions')->find($this->receiptId);
        $extraction = $receipt?->extractions->sortByDesc('attempt')->first();
        $payload = json_decode((string) json_encode($extraction?->normalized_data), true);
        $rawMerchant = is_array($payload) ? data_get($payload, 'parsed_fields.merchant') : null;
        if ($receipt instanceof Receipt && is_string($rawMerchant)) {
            $catalog->normalizeReceiptMerchant($receipt, $rawMerchant);
        }
    }
}
