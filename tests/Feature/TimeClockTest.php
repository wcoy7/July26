<?php

use Livewire\Livewire;

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
    $result = \App\Plugins\Geolocation::getCurrentPosition();
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

