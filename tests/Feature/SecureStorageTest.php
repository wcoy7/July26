<?php

use App\Plugins\SecureStorage;

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

test('it handles missing bridge gracefully when function is not mocked', function () {
    global $mockNativePhpCalls;
    // Temporarily empty the mock to simulate default failure
    $mockNativePhpCalls = [];

    $success = SecureStorage::set('test_key', 'test_value');
    expect($success)->toBeFalse();

    $value = SecureStorage::get('test_key');
    expect($value)->toBeNull();

    $deleted = SecureStorage::delete('test_key');
    expect($deleted)->toBeFalse();
});

test('it can set a secure value via the bridge', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['SecureStorage.Set'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['key'])->toBe('auth_token');
        expect($data['value'])->toBe('secret123');

        return json_encode(['success' => true]);
    };

    $success = SecureStorage::set('auth_token', 'secret123');
    expect($success)->toBeTrue();
});

test('it returns false if set fails on native side', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['SecureStorage.Set'] = function (string $payload) {
        return json_encode(['success' => false, 'error' => 'Keychain full']);
    };

    $success = SecureStorage::set('auth_token', 'secret123');
    expect($success)->toBeFalse();
});

test('it can retrieve a secure value via the bridge', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['SecureStorage.Get'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['key'])->toBe('auth_token');

        return json_encode(['success' => true, 'value' => 'secret123']);
    };

    $value = SecureStorage::get('auth_token');
    expect($value)->toBe('secret123');
});

test('it returns null if get fails or value is missing on native side', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['SecureStorage.Get'] = function (string $payload) {
        return json_encode(['success' => false, 'error' => 'Not found']);
    };

    $value = SecureStorage::get('auth_token');
    expect($value)->toBeNull();
});

test('it can delete a secure value via the bridge', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls['SecureStorage.Delete'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['key'])->toBe('auth_token');

        return json_encode(['success' => true]);
    };

    $success = SecureStorage::delete('auth_token');
    expect($success)->toBeTrue();
});
