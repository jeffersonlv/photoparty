<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use InvalidArgumentException;

/**
 * QR code gerado localmente com chillerlan/php-qrcode v6 (sem API externa).
 * Na v6 o formato é escolhido pela classe de saída (`outputInterface`).
 */
class QrCodeGenerator
{
    public const FORMATS = ['png', 'svg'];

    public function render(string $data, string $format): string
    {
        $options = match ($format) {
            'png' => new QROptions([
                'outputInterface' => QRGdImagePNG::class,
                'outputBase64' => false,
                'eccLevel' => EccLevel::M,
                'scale' => 12,
                'quietzoneSize' => 4,
            ]),
            'svg' => new QROptions([
                'outputInterface' => QRMarkupSVG::class,
                'outputBase64' => false,
                'eccLevel' => EccLevel::M,
                'quietzoneSize' => 4,
                'svgAddXmlHeader' => true,
            ]),
            default => throw new InvalidArgumentException('Formato de QR inválido.'),
        };

        return (new QRCode($options))->render($data);
    }

    public function contentType(string $format): string
    {
        return $format === 'svg' ? 'image/svg+xml' : 'image/png';
    }
}
