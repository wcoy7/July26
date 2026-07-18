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

test('home page renders the time clock by default', function () {
    $this->get(route('home'))
        ->assertSuccessful()
        ->assertSeeLivewire('time-clock');
});

test('time clock component can be rendered', function () {
    Livewire::test('time-clock')
        ->assertStatus(200);
});

test('keypad presses trigger strong 100ms vibration', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['Vibration.HasHaptics'] = fn () => json_encode(['success' => true, 'supported' => true]);
    $mockNativePhpCalls['Vibration.Vibrate'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['duration'])->toBe(100);
        expect((float) $data['intensity'])->toEqual(0.75);

        return json_encode(['success' => true]);
    };

    Livewire::test('time-clock')
        ->call('vibrateKeypad');
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

test('it renders the vibration modal button and modal markup', function () {
    Livewire::test('time-clock')
        ->assertSeeHtml('vibration-modal')
        ->assertSeeHtml('HAPTIC');
});

test('it renders the background tasks modal button and markup', function () {
    Livewire::test('time-clock')
        ->assertSeeHtml('background-tasks-modal')
        ->assertSeeHtml('TASKS');
});

test('it can create list update run and delete a background task via the modal actions', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls = [];
    $store = [];

    $mockNativePhpCalls['BackgroundTasks.Create'] = function (string $payload) use (&$store) {
        $data = json_decode($payload, true);
        $task = array_merge($data, [
            'id' => 'task-livewire-1',
            'createdAt' => '2026-07-18T00:00:00Z',
            'updatedAt' => '2026-07-18T00:00:00Z',
        ]);
        $store[$task['id']] = $task;

        return json_encode(['success' => true, 'task' => $task]);
    };

    $mockNativePhpCalls['BackgroundTasks.List'] = function () use (&$store) {
        return json_encode(['success' => true, 'tasks' => array_values($store)]);
    };

    $mockNativePhpCalls['BackgroundTasks.Get'] = function (string $payload) use (&$store) {
        $id = json_decode($payload, true)['id'] ?? '';
        if (! isset($store[$id])) {
            return json_encode(['success' => false, 'error' => 'not found']);
        }

        return json_encode(['success' => true, 'task' => $store[$id]]);
    };

    $mockNativePhpCalls['BackgroundTasks.Update'] = function (string $payload) use (&$store) {
        $data = json_decode($payload, true);
        $id = $data['id'] ?? '';
        if (! isset($store[$id])) {
            return json_encode(['success' => false, 'error' => 'not found']);
        }
        $store[$id] = array_merge($store[$id], $data);

        return json_encode(['success' => true, 'task' => $store[$id]]);
    };

    $mockNativePhpCalls['BackgroundTasks.Delete'] = function (string $payload) use (&$store) {
        $id = json_decode($payload, true)['id'] ?? '';
        unset($store[$id]);

        return json_encode(['success' => true]);
    };

    $mockNativePhpCalls['BackgroundTasks.Sync'] = fn () => json_encode(['success' => true, 'count' => 1]);

    $mockNativePhpCalls['BackgroundTasks.RunNow'] = function (string $payload) {
        $data = json_decode($payload, true);

        return json_encode([
            'success' => true,
            'results' => [[
                'id' => $data['id'] ?? 'task-livewire-1',
                'command' => 'inspire',
                'output' => 'Simplicity is the ultimate sophistication.',
                'success' => true,
            ]],
        ]);
    };

    Livewire::test('time-clock')
        ->set('bgTaskName', 'test-inspire')
        ->set('bgTaskCommand', 'inspire')
        ->set('bgTaskInterval', 15)
        ->call('bgCreate')
        ->assertSet('bgTaskId', 'task-livewire-1')
        ->assertSee('Task created')
        ->call('bgRefreshList')
        ->assertCount('bgTasks', 1)
        ->set('bgTaskInterval', 30)
        ->call('bgUpdate')
        ->assertSee('Task updated')
        ->call('bgSync')
        ->assertSee('synced')
        ->call('bgRunNow')
        ->assertSee('RunNow completed')
        ->assertSet('bgTaskOutput', fn ($out) => str_contains($out, 'Simplicity is the ultimate sophistication.'))
        ->call('bgDelete')
        ->assertSet('bgTaskId', '')
        ->assertSee('Task deleted');
});

test('it can trigger vibration actions via Livewire', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls = [];

    $mockNativePhpCalls['Vibration.HasHaptics'] = fn () => json_encode(['success' => true, 'supported' => true]);
    $mockNativePhpCalls['Vibration.Vibrate'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['duration'])->toBe(50);

        return json_encode(['success' => true]);
    };
    $mockNativePhpCalls['Vibration.PlayPattern'] = fn () => json_encode(['success' => true]);
    $mockNativePhpCalls['Vibration.Cancel'] = fn () => json_encode(['success' => true]);

    Livewire::test('time-clock')
        ->call('vibrateTap')
        ->assertSet('vibrationStatusMessage', 'Tap feedback sent.')
        ->call('vibrateSuccess')
        ->assertSet('vibrationStatusMessage', 'Success pattern played.')
        ->call('vibrateError')
        ->assertSet('vibrationStatusMessage', 'Error pattern played.')
        ->call('vibrateCancel')
        ->assertSet('vibrationStatusMessage', 'Vibration cancelled.');
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
