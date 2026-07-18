<?php

namespace App\Plugins;

/**
 * Fluent builder for barcode/QR scanning, matching nativephp/mobile-scanner API.
 *
 * @see https://nativephp.com/plugins/nativephp/mobile-scanner
 */
class PendingBarcodeScan
{
    protected ?string $prompt = null;

    protected bool $continuous = false;

    /** @var list<string> */
    protected array $formats = ['qr'];

    protected ?string $id = null;

    protected bool $started = false;

    public function prompt(string $prompt): self
    {
        $this->prompt = $prompt;

        return $this;
    }

    public function continuous(bool $continuous = true): self
    {
        $this->continuous = $continuous;

        return $this;
    }

    /**
     * @param  list<string>  $formats  qr, ean13, ean8, code128, code39, upca, upce, all
     */
    public function formats(array $formats): self
    {
        $this->formats = array_values($formats);

        return $this;
    }

    public function id(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Open the native scanner UI.
     */
    public function scan(): void
    {
        if ($this->started) {
            return;
        }

        $this->started = true;

        if (! function_exists('nativephp_call')) {
            return;
        }

        nativephp_call('Scanner.Scan', json_encode([
            'prompt' => $this->prompt ?? 'Scan barcode',
            'continuous' => $this->continuous,
            'formats' => $this->formats,
            'id' => $this->id,
        ]));
    }

    public function __destruct()
    {
        if (! $this->started) {
            $this->scan();
        }
    }
}
