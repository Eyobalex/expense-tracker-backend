<?php

namespace App\Domain\Transactions\Enums;

enum SourceType: string
{
    case Manual = 'manual';
    case ReceiptOcr = 'receipt_ocr';
    case TransferReceiptOcr = 'transfer_receipt_ocr';
    case Import = 'import';
    case Sync = 'sync';
}
