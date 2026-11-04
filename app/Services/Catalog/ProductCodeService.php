<?php

declare(strict_types=1);

namespace App\Services\Catalog;

use App\Models\Business;
use App\Models\Product;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Service for product QR/Barcode generation, validation, cross-conflict resolution,
 * and label rendering.
 */
class ProductCodeService
{
    public const CODE_PREFIX = 'NXP-';
    public const MAX_LENGTH = 255;

    /**
     * Validate code format: string, max 255, no control characters, non-empty when trimmed.
     * Returns an error message string if invalid, or null if valid.
     */
    public function validateCode(?string $code, string $field = 'barcode'): ?string
    {
        if ($code === null || trim($code) === '') {
            return "Field {$field} tidak boleh kosong.";
        }

        if (mb_strlen($code) > self::MAX_LENGTH) {
            return "Field {$field} maksimal " . self::MAX_LENGTH . " karakter.";
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $code)) {
            return "Field {$field} mengandung karakter kontrol yang tidak valid.";
        }

        return null;
    }

    /**
     * Check if a barcode conflicts with another product's barcode or SKU within the same business.
     * Same product having sku == barcode is permitted.
     */
    public function checkBarcodeConflict(int $businessId, string $barcode, ?int $ignoreProductId = null): ?string
    {
        $barcode = trim($barcode);
        if ($barcode === '') {
            return null;
        }

        // 1. Check collision with another product's barcode
        $barcodeExists = Product::query()
            ->where('business_id', $businessId)
            ->where('barcode', $barcode)
            ->when($ignoreProductId !== null, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($barcodeExists) {
            return "Barcode '{$barcode}' sudah digunakan oleh produk lain dalam bisnis ini.";
        }

        // 2. Check cross-collision with another product's SKU
        $skuConflict = Product::query()
            ->where('business_id', $businessId)
            ->where('sku', $barcode)
            ->when($ignoreProductId !== null, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($skuConflict) {
            return "Barcode '{$barcode}' bentrok dengan SKU produk lain dalam bisnis ini.";
        }

        return null;
    }

    /**
     * Check if an SKU conflicts with another product's SKU or barcode within the same business.
     * Same product having sku == barcode is permitted.
     */
    public function checkSkuConflict(int $businessId, string $sku, ?int $ignoreProductId = null): ?string
    {
        $sku = trim($sku);
        if ($sku === '') {
            return null;
        }

        // 1. Check collision with another product's SKU
        $skuExists = Product::query()
            ->where('business_id', $businessId)
            ->where('sku', $sku)
            ->when($ignoreProductId !== null, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($skuExists) {
            return "SKU '{$sku}' sudah digunakan oleh produk lain dalam bisnis ini.";
        }

        // 2. Check cross-collision with another product's barcode
        $barcodeConflict = Product::query()
            ->where('business_id', $businessId)
            ->where('barcode', $sku)
            ->when($ignoreProductId !== null, fn ($q) => $q->where('id', '!=', $ignoreProductId))
            ->exists();

        if ($barcodeConflict) {
            return "SKU '{$sku}' bentrok dengan barcode produk lain dalam bisnis ini.";
        }

        return null;
    }

    /**
     * Generate a unique, stable internal barcode with NXP- prefix and UUID.
     */
    public function generateInternalBarcode(Business $business): string
    {
        $maxAttempts = 10;
        for ($i = 0; $i < $maxAttempts; $i++) {
            $code = self::CODE_PREFIX . strtoupper(Str::uuid()->toString());

            $conflict = $this->checkBarcodeConflict($business->id, $code);
            if ($conflict === null) {
                return $code;
            }
        }

        throw new \RuntimeException('Gagal membuat kode barcode internal unik setelah beberapa percobaan.');
    }

    /**
     * Idempotently assign a barcode to a product.
     * If product already has a barcode and $barcode is null, the existing barcode is preserved.
     */
    public function assignBarcode(Product $product, ?string $barcode = null): Product
    {
        return DB::transaction(function () use ($product, $barcode): Product {
            /** @var Product $locked */
            $locked = Product::query()
                ->where('business_id', $product->business_id)
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Idempotent: if already has barcode and no new barcode is explicitly passed, preserve it
            if ($barcode === null) {
                if ($locked->barcode !== null && trim($locked->barcode) !== '') {
                    return $locked;
                }

                $business = Business::findOrFail($locked->business_id);
                $newBarcode = $this->generateInternalBarcode($business);
            } else {
                $trimmed = trim($barcode);
                $validationError = $this->validateCode($trimmed, 'barcode');
                if ($validationError !== null) {
                    throw new InvalidArgumentException($validationError);
                }

                $conflict = $this->checkBarcodeConflict($locked->business_id, $trimmed, $locked->id);
                if ($conflict !== null) {
                    throw new InvalidArgumentException($conflict);
                }

                $newBarcode = $trimmed;
            }

            $locked->barcode = $newBarcode;
            $locked->save();

            return $locked;
        });
    }

    /**
     * Render QR code as SVG string.
     */
    public function renderQrSvg(string $code, int $size = 280): string
    {
        $code = trim($code);
        if ($code === '') {
            throw new InvalidArgumentException('Kode QR tidak boleh kosong.');
        }

        $renderer = new ImageRenderer(
            new RendererStyle($size, 2),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($code);
    }

    /**
     * Render full printable label as PNG string with white background,
     * product name header, centered QR code, and human-readable text.
     */
    public function renderLabelPng(Product $product, int $qrSize = 220): string
    {
        $code = (string) ($product->barcode ?: $product->sku);
        if (trim($code) === '') {
            throw new InvalidArgumentException('Produk tidak memiliki kode scan.');
        }

        $padding = 24;
        $width = $qrSize + ($padding * 2);
        $headerHeight = 50;
        $footerHeight = 45;
        $height = $qrSize + $headerHeight + $footerHeight + ($padding * 2);

        $image = imagecreatetruecolor($width, $height);
        if (! $image) {
            throw new \RuntimeException('Gagal menginisialisasi gambar label GD.');
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 17, 24, 39);      // Slate 900
        $gray = imagecolorallocate($image, 100, 116, 139);     // Slate 500
        $border = imagecolorallocate($image, 226, 232, 240);  // Slate 200

        imagefilledrectangle($image, 0, 0, $width, $height, $white);
        // Outer subtle border
        imagerectangle($image, 0, 0, $width - 1, $height - 1, $border);

        // Header: Product Name
        $name = mb_strimwidth((string) $product->name, 0, 28, '...');
        $font = 4; // Built-in GD font 4
        $nameWidth = imagefontwidth($font) * strlen($name);
        $nameX = max(10, (int) (($width - $nameWidth) / 2));
        imagestring($image, $font, $nameX, $padding + 6, $name, $black);

        // Subheader: SKU (small)
        $skuText = 'SKU: ' . mb_strimwidth((string) $product->sku, 0, 30, '...');
        $fontSmall = 2;
        $skuWidth = imagefontwidth($fontSmall) * strlen($skuText);
        $skuX = max(10, (int) (($width - $skuWidth) / 2));
        imagestring($image, $fontSmall, $skuX, $padding + 26, $skuText, $gray);

        // Draw QR Code Matrix
        $qr = Encoder::encode($code, ErrorCorrectionLevel::M());
        $matrix = $qr->getMatrix();
        $matrixSize = $matrix->getWidth();
        $moduleSize = (int) floor($qrSize / $matrixSize);
        $actualQrSize = $moduleSize * $matrixSize;
        $qrStartX = (int) (($width - $actualQrSize) / 2);
        $qrStartY = $padding + $headerHeight + (int) (($qrSize - $actualQrSize) / 2);

        for ($r = 0; $r < $matrixSize; $r++) {
            for ($c = 0; $c < $matrixSize; $c++) {
                if ($matrix->get($c, $r)) {
                    $x1 = $qrStartX + ($c * $moduleSize);
                    $y1 = $qrStartY + ($r * $moduleSize);
                    $x2 = $x1 + $moduleSize - 1;
                    $y2 = $y1 + $moduleSize - 1;
                    imagefilledrectangle($image, $x1, $y1, $x2, $y2, $black);
                }
            }
        }

        // Footer: Human-readable Code
        $codeText = mb_strimwidth($code, 0, 32, '...');
        $codeFont = 3;
        $codeWidth = imagefontwidth($codeFont) * strlen($codeText);
        $codeX = max(10, (int) (($width - $codeWidth) / 2));
        $codeY = $height - $padding - 22;
        imagestring($image, $codeFont, $codeX, $codeY, $codeText, $black);

        ob_start();
        imagepng($image);
        $pngData = (string) ob_get_clean();
        imagedestroy($image);

        return $pngData;
    }
}
