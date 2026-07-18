<?php

use App\Plugins\Geolocation;
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

test('time clock component renders successfully', function () {
    $this->get('/')
        ->assertStatus(200)
        ->assertSeeLivewire('time-clock');
});

test('it can validate PIN and show actions', function () {
    Livewire::test('time-clock')
        ->assertSet('validatedEmployee', null)
        ->call('validatePin', '1234')
        ->assertSet('validatedEmployee', 'John Doe')
        ->assertSet('validatedPin', '1234');
});

test('it can clock in staff member', function () {
    Livewire::test('time-clock')
        ->call('validatePin', '1234')
        ->call('performAction', 'in')
        ->assertSet('status', 'John Doe successfully clocked in!')
        ->assertSet('validatedEmployee', null)
        ->assertSet('validatedPin', null)
        ->assertCount('history', 3);
});

test('it can clock out staff member', function () {
    Livewire::test('time-clock')
        // Jane Smith starts clocked_in in mount() mock data
        ->call('validatePin', '5678')
        ->call('performAction', 'out')
        ->assertSet('status', 'Jane Smith successfully clocked out!')
        ->assertSet('validatedEmployee', null)
        ->assertSet('validatedPin', null)
        ->assertCount('history', 3);
});

test('it can start and end breaks', function () {
    Livewire::test('time-clock')
        // Jane Smith starts clocked_in
        ->call('validatePin', '5678')
        ->call('performAction', 'start_break')
        ->assertSet('status', 'Jane Smith started break.')
        // End break
        ->call('validatePin', '5678')
        ->call('performAction', 'end_break')
        ->assertSet('status', 'Jane Smith ended break.');
});

test('geolocation plugin handles missing bridge gracefully', function () {
    $result = Geolocation::getCurrentPosition();
    expect($result)->toBeArray();
    expect(array_key_exists('success', $result))->toBeTrue();
    expect(array_key_exists('error', $result))->toBeTrue();
    expect($result['success'])->toBeFalse();
    expect($result['error'])->toBeString();
});

test('it renders the geolocation map container with wire:ignore', function () {
    Livewire::test('time-clock')
        ->assertSeeHtml('wire:ignore');
});

test('it renders the secure storage modal button and modal markup', function () {
    Livewire::test('time-clock')
        ->assertSeeHtml('secure-storage-modal')
        ->assertSeeHtml('KEYCHAIN');
});

test('it can save, retrieve, and delete values via Livewire interface', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls = [];

    // Mock Set
    $mockNativePhpCalls['SecureStorage.Set'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['key'])->toBe('livewire_key');
        expect($data['value'])->toBe('livewire_val');

        return json_encode(['success' => true]);
    };

    // Mock Get
    $mockNativePhpCalls['SecureStorage.Get'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['key'])->toBe('livewire_key');

        return json_encode(['success' => true, 'value' => 'livewire_val']);
    };

    // Mock Delete
    $mockNativePhpCalls['SecureStorage.Delete'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['key'])->toBe('livewire_key');

        return json_encode(['success' => true]);
    };

    Livewire::test('time-clock')
        ->set('secureStorageKey', 'livewire_key')
        ->set('secureStorageValue', 'livewire_val')
        ->call('secureSave')
        ->assertSet('secureStorageStatusMessage', 'Value saved successfully!')
        ->assertSet('secureStorageValue', '')
        ->call('secureGet')
        ->assertSet('secureStorageStatusMessage', 'Value retrieved successfully!')
        ->assertSet('secureStorageResult', 'livewire_val')
        ->call('secureDelete')
        ->assertSet('secureStorageStatusMessage', 'Value deleted successfully!')
        ->assertSet('secureStorageResult', '');
});
