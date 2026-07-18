<?php

use Livewire\Livewire;

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

test('vibration tester component can be rendered', function () {
    Livewire::test('vibration-tester')
        ->assertSee('Haptic Lab')
        ->assertSee('Very Light')
        ->assertSee('Light')
        ->assertSee('Medium')
        ->assertSee('Strong')
        ->assertSee('Max')
        ->assertSee('Play custom')
        ->assertSee('Cancel vibration');
});

test('it vibrates at a selected intensity level', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['Vibration.HasHaptics'] = fn () => json_encode(['success' => true, 'supported' => true]);
    $mockNativePhpCalls['Vibration.Vibrate'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['intensity'])->toEqual(1.0);
        expect($data['duration'])->toBe(100);

        return json_encode(['success' => true]);
    };

    Livewire::test('vibration-tester')
        ->call('vibrateAtLevel', 1.0)
        ->assertSet('intensity', 1.0)
        ->assertSee('intensity 100%');
});

test('it vibrates with a selected duration', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['Vibration.HasHaptics'] = fn () => json_encode(['success' => true, 'supported' => true]);
    $mockNativePhpCalls['Vibration.Vibrate'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['duration'])->toBe(500);

        return json_encode(['success' => true]);
    };

    Livewire::test('vibration-tester')
        ->call('vibrateDuration', 500)
        ->assertSet('duration', 500)
        ->assertSee('500ms');
});

test('it plays presets and can cancel', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['Vibration.HasHaptics'] = fn () => json_encode(['success' => true, 'supported' => true]);
    $mockNativePhpCalls['Vibration.PlayPattern'] = fn () => json_encode(['success' => true]);
    $mockNativePhpCalls['Vibration.Cancel'] = fn () => json_encode(['success' => true]);

    Livewire::test('vibration-tester')
        ->call('playPreset', 'success')
        ->assertSee('Preset “success” played.')
        ->call('playPatternDemo')
        ->assertSee('Heartbeat pattern played.')
        ->call('cancel')
        ->assertSee('Vibration cancelled.');
});

test('it plays custom vibration with current settings', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['Vibration.HasHaptics'] = fn () => json_encode(['success' => true, 'supported' => true]);
    $mockNativePhpCalls['Vibration.Vibrate'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['duration'])->toBe(250);
        expect((float) $data['intensity'])->toEqual(0.7);
        expect((float) $data['sharpness'])->toEqual(0.9);

        return json_encode(['success' => true]);
    };

    Livewire::test('vibration-tester')
        ->set('duration', 250)
        ->set('intensity', 0.7)
        ->set('sharpness', 0.9)
        ->call('playCustom')
        ->assertSee('Vibrated 250ms');
});
