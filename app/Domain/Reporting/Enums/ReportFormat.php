<?php

namespace App\Domain\Reporting\Enums;

enum ReportFormat: string
{
    case Csv = 'csv';
    case Xlsx = 'xlsx';
    case Pdf = 'pdf';
    case Json = 'json';
    case Zip = 'zip';
}
