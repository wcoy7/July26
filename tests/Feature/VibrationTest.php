<?php

use App\Plugins\Vibration;
use App\Plugins\VibrationPreset;

// Set up global mock for nativephp_call if not defined
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

test('it handles missing bridge gracefully', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls = [];

    expect(Vibration::vibrate(100, 0.5))->toBeFalse();
    expect(Vibration::hasHaptics())->toBeFalse();
    expect(Vibration::cancelVibration())->toBeFalse();
    expect(Vibration::playPattern([['type' => 'vibrate', 'duration' => 50, 'intensity' => 0.5]]))->toBeFalse();
});

test('it can vibrate via the bridge', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['Vibration.Vibrate'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['duration'])->toBe(200);
        expect($data['intensity'])->toBe(0.8);
        expect($data['sharpness'])->toBe(0.5);

        return json_encode(['success' => true]);
    };

    expect(Vibration::vibrate(200, 0.8, 0.5))->toBeTrue();
});

test('it clamps duration and intensity', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['Vibration.Vibrate'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['duration'])->toBe(5000);
        expect((float) $data['intensity'])->toBe(1.0);

        return json_encode(['success' => true]);
    };

    expect(Vibration::vibrate(99999, 2.5))->toBeTrue();
});

test('it returns false when vibrate fails on native side', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['Vibration.Vibrate'] = fn () => json_encode(['success' => false, 'error' => 'No haptics']);

    expect(Vibration::vibrate(100, 0.5))->toBeFalse();
});

test('it can check haptic support via the bridge', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['Vibration.HasHaptics'] = fn () => json_encode(['success' => true, 'supported' => true]);

    expect(Vibration::hasHaptics())->toBeTrue();
});

test('it can cancel vibration via the bridge', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['Vibration.Cancel'] = fn () => json_encode(['success' => true]);

    expect(Vibration::cancelVibration())->toBeTrue();
});

test('it can play a fluent pattern', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['Vibration.PlayPattern'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['steps'])->toHaveCount(3);
        expect($data['steps'][0]['type'])->toBe('vibrate');
        expect($data['steps'][1]['type'])->toBe('pause');
        expect($data['steps'][2]['duration'])->toBe(200);

        return json_encode(['success' => true]);
    };

    $ok = Vibration::pattern()
        ->vibrate(100, 0.8, 0.5)
        ->pause(50)
        ->vibrate(200, 1.0)
        ->play();

    expect($ok)->toBeTrue();
});

test('it can play a preset pattern', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['Vibration.PlayPattern'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['steps'])->not->toBeEmpty();

        return json_encode(['success' => true]);
    };

    expect(Vibration::preset('success')->play())->toBeTrue();
    expect(Vibration::preset(VibrationPreset::Error)->play())->toBeTrue();
});

test('it throws for unknown preset names', function () {
    Vibration::preset('not-a-real-preset');
})->throws(InvalidArgumentException::class);
