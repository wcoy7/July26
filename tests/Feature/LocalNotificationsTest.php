<?php

use App\Plugins\LocalNotifications;

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

test('it handles missing bridge for notifications', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls = [];

    expect(LocalNotifications::requestPermission())->toBeFalse();
    expect(LocalNotifications::hasPermission())->toBeFalse();
    expect(LocalNotifications::show('t', 'b')['success'])->toBeFalse();
    expect(LocalNotifications::schedule('t', 'b', 5)['success'])->toBeFalse();
    expect(LocalNotifications::cancel('x'))->toBeFalse();
    expect(LocalNotifications::cancelAll())->toBeFalse();
});

test('it can request and check permission', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['LocalNotifications.RequestPermission'] = fn () => json_encode([
        'success' => true,
        'granted' => true,
    ]);
    $mockNativePhpCalls['LocalNotifications.HasPermission'] = fn () => json_encode([
        'success' => true,
        'granted' => true,
    ]);

    expect(LocalNotifications::requestPermission())->toBeTrue();
    expect(LocalNotifications::hasPermission())->toBeTrue();
});

test('it can show a notification', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['LocalNotifications.Show'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['title'])->toBe('Hello');
        expect($data['body'])->toBe('World');
        expect($data['id'])->toBeString()->not->toBeEmpty();

        return json_encode(['success' => true, 'id' => $data['id']]);
    };

    $result = LocalNotifications::show('Hello', 'World');
    expect($result['success'])->toBeTrue()
        ->and($result['id'])->toBeString();
});

test('it can schedule a notification', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['LocalNotifications.Schedule'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['delaySeconds'])->toBe(10);
        expect($data['id'])->toBe('custom-id');

        return json_encode(['success' => true, 'id' => 'custom-id', 'delaySeconds' => 10]);
    };

    $result = LocalNotifications::schedule('Later', 'See you', 10, 'custom-id');
    expect($result['success'])->toBeTrue()
        ->and($result['id'])->toBe('custom-id');
});

test('it clamps schedule delay to a valid range', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['LocalNotifications.Schedule'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['delaySeconds'])->toBe(1);

        return json_encode(['success' => true, 'id' => 'x', 'delaySeconds' => 1]);
    };

    LocalNotifications::schedule('t', 'b', 0);
});

test('it can cancel one or all notifications', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['LocalNotifications.Cancel'] = function (string $payload) {
        expect(json_decode($payload, true)['id'])->toBe('abc');

        return json_encode(['success' => true]);
    };
    $mockNativePhpCalls['LocalNotifications.CancelAll'] = fn () => json_encode(['success' => true]);

    expect(LocalNotifications::cancel('abc'))->toBeTrue();
    expect(LocalNotifications::cancelAll())->toBeTrue();
});
