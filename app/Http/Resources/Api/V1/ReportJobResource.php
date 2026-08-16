<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ReportJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ReportJob */
class ReportJobResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var ReportJob $report */
        $report = $this->resource;

        return [
            'id' => $report->getKey(), 'type' => $report->type, 'format' => $report->format, 'filters' => $report->filters,
            'base_currency_code' => $report->base_currency_code, 'timezone' => $report->timezone, 'status' => $report->status,
            'progress' => $report->progress, 'artifact_filename' => $report->artifact_filename, 'mime_type' => $report->mime_type,
            'byte_size' => $report->byte_size, 'checksum_sha256' => $report->checksum_sha256, 'error_code' => $report->error_code,
            'expires_at' => $report->expires_at->toISOString(), 'started_at' => $report->started_at?->toISOString(),
            'completed_at' => $report->completed_at?->toISOString(), 'download_available' => $report->status === 'completed' && $report->private_object_key !== null && $report->expires_at->isFuture(),
            'created_at' => $report->created_at?->toISOString(), 'updated_at' => $report->updated_at?->toISOString(),
        ];
    }
}
