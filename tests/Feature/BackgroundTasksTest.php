<?php

use App\Plugins\BackgroundTasks;

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

test('it handles missing bridge gracefully for all CRUD operations', function () {
    global $mockNativePhpCalls;
    $mockNativePhpCalls = [];

    expect(BackgroundTasks::create(['name' => 'sync:data']))->toBeNull();
    expect(BackgroundTasks::get('abc'))->toBeNull();
    expect(BackgroundTasks::list())->toBe([]);
    expect(BackgroundTasks::update('abc', ['enabled' => false]))->toBeNull();
    expect(BackgroundTasks::delete('abc'))->toBeFalse();
});

test('it requires a name when creating a task', function () {
    expect(BackgroundTasks::create(['intervalMinutes' => 30]))->toBeNull();
});

test('it can create a background task via the bridge', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['BackgroundTasks.Create'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['name'])->toBe('sync:data');
        expect($data['command'])->toBe('sync:data');
        expect($data['intervalMinutes'])->toBe(15);
        expect($data['enabled'])->toBeTrue();
        expect($data['longRunning'])->toBeFalse();
        expect($data['constraints']['onWifi'])->toBeFalse();

        return json_encode([
            'success' => true,
            'task' => array_merge($data, [
                'id' => 'task-1',
                'createdAt' => '2026-07-18T00:00:00Z',
                'updatedAt' => '2026-07-18T00:00:00Z',
            ]),
        ]);
    };

    $task = BackgroundTasks::create([
        'name' => 'sync:data',
        'intervalMinutes' => 15,
    ]);

    expect($task)->toBeArray()
        ->and($task['id'])->toBe('task-1')
        ->and($task['name'])->toBe('sync:data');
});

test('it clamps interval minutes to the mobile minimum of 15', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['BackgroundTasks.Create'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['intervalMinutes'])->toBe(15);

        return json_encode([
            'success' => true,
            'task' => array_merge($data, ['id' => 'task-clamp']),
        ]);
    };

    $task = BackgroundTasks::create([
        'name' => 'quick:poll',
        'intervalMinutes' => 1,
    ]);

    expect($task['intervalMinutes'])->toBe(15);
});

test('it can get a task by id', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['BackgroundTasks.Get'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['id'])->toBe('task-1');

        return json_encode([
            'success' => true,
            'task' => ['id' => 'task-1', 'name' => 'sync:data', 'command' => 'sync:data'],
        ]);
    };

    $task = BackgroundTasks::get('task-1');
    expect($task['name'])->toBe('sync:data');
});

test('it can list tasks', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['BackgroundTasks.List'] = fn () => json_encode([
        'success' => true,
        'tasks' => [
            ['id' => 'a', 'name' => 'sync:data'],
            ['id' => 'b', 'name' => 'backup:run'],
        ],
    ]);

    $tasks = BackgroundTasks::list();
    expect($tasks)->toHaveCount(2)
        ->and($tasks[1]['name'])->toBe('backup:run');
});

test('it can update a task', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['BackgroundTasks.Update'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['id'])->toBe('task-1');
        expect($data['enabled'])->toBeFalse();
        expect($data['intervalMinutes'])->toBe(60);
        expect($data['constraints']['whileCharging'])->toBeTrue();

        return json_encode([
            'success' => true,
            'task' => [
                'id' => 'task-1',
                'name' => 'sync:data',
                'enabled' => false,
                'intervalMinutes' => 60,
                'constraints' => $data['constraints'],
            ],
        ]);
    };

    $task = BackgroundTasks::update('task-1', [
        'enabled' => false,
        'intervalMinutes' => 60,
        'constraints' => ['whileCharging' => true],
    ]);

    expect($task['enabled'])->toBeFalse()
        ->and($task['intervalMinutes'])->toBe(60)
        ->and($task['constraints']['whileCharging'])->toBeTrue();
});

test('it can delete a task', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['BackgroundTasks.Delete'] = function (string $payload) {
        $data = json_decode($payload, true);
        expect($data['id'])->toBe('task-1');

        return json_encode(['success' => true]);
    };

    expect(BackgroundTasks::delete('task-1'))->toBeTrue();
});

test('it returns false when delete fails on native side', function () {
    global $mockNativePhpCalls;

    $mockNativePhpCalls['BackgroundTasks.Delete'] = fn () => json_encode([
        'success' => false,
        'error' => 'Task not found',
    ]);

    expect(BackgroundTasks::delete('missing'))->toBeFalse();
});
