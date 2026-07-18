<?php

namespace App\Plugins;

/**
 * Custom barcode/QR scanner plugin inspired by nativephp/mobile-scanner.
 *
 * Opens a native camera UI (iOS AVFoundation / Android ML Kit + CameraX).
 * Results are delivered via Livewire #[OnNative(\Native\Mobile\Events\Scanner\CodeScanned::class)].
 *
 * @see https://nativephp.com/plugins/nativephp/mobile-scanner
 */
class Scanner
{
    /**
     * Start a fluent scan session.
     *
     * Example:
     * Scanner::scan()->prompt('Scan product')->formats(['qr', 'ean13'])->id('checkout');
     */
    public static function scan(): PendingBarcodeScan
    {
        return new PendingBarcodeScan;
    }
}
