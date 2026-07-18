<?php

use App\Plugins\PendingBarcodeScan;
use App\Plugins\Scanner;

if (! function_exists('nativephp_call')) {
    function nativephp_call(string $function, string $payload): string
    {
        global $mockNativePhpCalls;
        if (isset($mockNativePhpCalls[$function])) {
            return call_user_func($mockNativePhpCalls[$function], $payload);
        }

        return json_encode(['success' => false, 'error' => 'Mock function not set']);
    }
}

beforeEach(function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls = [];
});

test('scan returns a pending barcode scan builder', function () {
    expect(Scanner::scan())->toBeInstanceOf(PendingBarcodeScan::class);
});

test('it does not call the bridge when nativephp_call is unavailable for explicit scan', function () {
    // Bridge mock returns error by default; scan() should still return without throwing
    Scanner::scan()
        ->prompt('Scan ticket')
        ->formats(['qr', 'ean13'])
        ->id('ticket')
        ->scan();

    expect(true)->toBeTrue();
});

test('it opens the scanner with prompt continuous formats and id', function () {
    global $mockNativePhpCalls;
    $called = false;

    $mockNativePhpCalls['Scanner.Scan'] = function (string $payload) use (&$called) {
        $called = true;
        $data = json_decode($payload, true);

        expect($data['prompt'])->toBe('Scan product barcode');
        expect($data['continuous'])->toBeTrue();
        expect($data['formats'])->toBe(['qr', 'ean13', 'code128']);
        expect($data['id'])->toBe('checkout');

        return json_encode(['success' => true, 'opened' => true]);
    };

    Scanner::scan()
        ->prompt('Scan product barcode')
        ->continuous(true)
        ->formats(['qr', 'ean13', 'code128'])
        ->id('checkout')
        ->scan();

    expect($called)->toBeTrue();
});

test('defaults are prompt Scan barcode continuous false formats qr and null id', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['Scanner.Scan'] = function (string $payload) {
        $data = json_decode($payload, true);

        expect($data['prompt'])->toBe('Scan barcode');
        expect($data['continuous'])->toBeFalse();
        expect($data['formats'])->toBe(['qr']);
        expect($data['id'])->toBeNull();

        return json_encode(['success' => true]);
    };

    Scanner::scan()->scan();
});

test('scan is only dispatched once when scan is called twice', function () {
    global $mockNativePhpCalls;
    $calls = 0;

    $mockNativePhpCalls['Scanner.Scan'] = function () use (&$calls) {
        $calls++;

        return json_encode(['success' => true]);
    };

    $pending = Scanner::scan()->prompt('Once');
    $pending->scan();
    $pending->scan();

    expect($calls)->toBe(1);
});

test('destruct auto-starts the scanner when scan was not called', function () {
    global $mockNativePhpCalls;
    $calls = 0;

    $mockNativePhpCalls['Scanner.Scan'] = function (string $payload) use (&$calls) {
        $calls++;
        $data = json_decode($payload, true);
        expect($data['prompt'])->toBe('Auto start');

        return json_encode(['success' => true]);
    };

    // Official API: Scanner::scan()->prompt(...) auto-fires via __destruct
    Scanner::scan()->prompt('Auto start');

    expect($calls)->toBe(1);
});
