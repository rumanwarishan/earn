<?php

declare(strict_types=1);

namespace App\Services;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;

final class QrCodeService
{
    public static function dataUri(string $data, int $size = 220): string
    {
        $result = Builder::create()
            ->writer(new PngWriter())
            ->data($data)
            ->size($size)
            ->margin(8)
            ->build();

        return $result->getDataUri();
    }
}
