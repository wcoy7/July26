<?php

use App\Plugins\BackgroundTasks;
use App\Plugins\Geolocation;
use App\Plugins\Vibration;
use Livewire\Component;

new class extends Component
{
    public string $status = '';

    public array $history = [];

    public ?float $latitude = null;

    public ?float $longitude = null;

    public string $secureStorageKey = '';

    public string $secureStorageValue = '';

    public string $secureStorageResult = '';

    public string $secureStorageStatusMessage = '';

    public string $vibrationStatusMessage = '';

    public bool $supportsHaptics = false;

    // Background Tasks test modal state
    public string $bgTaskName = 'inspire';

    public string $bgTaskCommand = 'inspire';

    public int $bgTaskInterval = 15;

    public string $bgTaskId = '';

    public string $bgTaskStatusMessage = '';

    public string $bgTaskOutput = '';

    /** @var list<array<string, mixed>> */
    public array $bgTasks = [];

    // Track state of validated employee
    public ?string $validatedEmployee = null;
    public ?string $validatedPin = null;

    // Persisted employee states (in-memory)
    public array $employeeStates = [
        '1234' => 'clocked_out',
        '5678' => 'clocked_in',
        '9012' => 'clocked_out',
    ];

    public function mount(): void
    {
        $this->supportsHaptics = \App\Plugins\Vibration::hasHaptics();
        // Initial mock history events
        $this->history = [
            [
                'name' => 'John Doe',
                'action' => 'Clock Out',
                'time' => '05:00:12 PM',
                'date' => now()->subDay()->format('M d, Y'),
                'lat' => 37.7749,
                'lon' => -122.4194
            ],
            [
                'name' => 'Jane Smith',
                'action' => 'Clock In',
                'time' => '07:55:04 AM',
                'date' => now()->format('M d, Y'),
                'lat' => 37.7892,
                'lon' => -122.4014
            ]
        ];

        $this->refreshLocation();
    }

    public function refreshLocation(): void
    {
        $location = Geolocation::getCurrentPosition();
        if ($location['success'] ?? false) {
            $this->latitude = $location['latitude'];
            $this->longitude = $location['longitude'];
        } else {
            $this->latitude = 37.7749;
            $this->longitude = -122.4194;
        }
    }

    /**
     * Strong 100ms haptic feedback for keypad presses.
     */
    public function vibrateKeypad(): void
    {
        Vibration::vibrate(duration: 100, intensity: 0.75);
    }

    public function validatePin(string $pin): void
    {
        $this->status = '';

        $name = match ($pin) {
            '1234' => 'John Doe',
            '5678' => 'Jane Smith',
            '9012' => 'Bob Johnson',
            default => null
        };

        if ($name === null) {
            $this->status = 'Error: Invalid PIN code.';

            return;
        }

        $this->validatedEmployee = $name;
        $this->validatedPin = $pin;

        // Refresh GPS coordinates when PIN is validated
        $this->refreshLocation();
    }

    public function cancel(): void
    {
        $this->validatedEmployee = null;
        $this->validatedPin = null;
        $this->status = '';
        $this->secureStorageKey = '';
        $this->secureStorageValue = '';
        $this->secureStorageResult = '';
        $this->secureStorageStatusMessage = '';
    }

    public function performAction(string $action): void
    {
        if ($this->validatedPin === null || $this->validatedEmployee === null) {
            return;
        }

        $pin = $this->validatedPin;
        $name = $this->validatedEmployee;
        $currentState = $this->employeeStates[$pin] ?? 'clocked_out';

        // Refresh location
        $this->refreshLocation();

        $timestamp = now()->format('h:i:s A');
        $dateStr = now()->format('M d, Y');

        if ($action === 'in') {
            $this->employeeStates[$pin] = 'clocked_in';
            $this->logEvent($name, 'Clock In', $timestamp, $dateStr);
            $this->status = "{$name} successfully clocked in!";
        } elseif ($action === 'start_break') {
            $this->employeeStates[$pin] = 'on_break';
            $this->logEvent($name, 'Start Break', $timestamp, $dateStr);
            $this->status = "{$name} started break.";
        } elseif ($action === 'end_break') {
            $this->employeeStates[$pin] = 'clocked_in';
            $this->logEvent($name, 'End Break', $timestamp, $dateStr);
            $this->status = "{$name} ended break.";
        } elseif ($action === 'out') {
            // If they are on break, automatically end the break first
            if ($currentState === 'on_break') {
                $this->logEvent($name, 'End Break', $timestamp, $dateStr);
            }
            $this->employeeStates[$pin] = 'clocked_out';
            $this->logEvent($name, 'Clock Out', $timestamp, $dateStr);
            $this->status = "{$name} successfully clocked out!";
        }

        // Return to PIN input screen
        $this->validatedEmployee = null;
        $this->validatedPin = null;
    }

    public function secureSave(): void
    {
        $this->secureStorageStatusMessage = '';
        if (empty($this->secureStorageKey)) {
            $this->secureStorageStatusMessage = 'Error: Key cannot be empty.';
            return;
        }

        $success = \App\Plugins\SecureStorage::set($this->secureStorageKey, $this->secureStorageValue);
        if ($success) {
            $this->secureStorageStatusMessage = 'Value saved successfully!';
            $this->secureStorageValue = '';
        } else {
            $this->secureStorageStatusMessage = 'Error: Failed to save value.';
        }
    }

    public function secureGet(): void
    {
        $this->secureStorageStatusMessage = '';
        if (empty($this->secureStorageKey)) {
            $this->secureStorageStatusMessage = 'Error: Key cannot be empty.';
            return;
        }

        $value = \App\Plugins\SecureStorage::get($this->secureStorageKey);
        if ($value !== null) {
            $this->secureStorageResult = $value;
            $this->secureStorageStatusMessage = 'Value retrieved successfully!';
        } else {
            $this->secureStorageResult = '';
            $this->secureStorageStatusMessage = 'Error: Key not found or error occurred.';
        }
    }

    public function secureDelete(): void
    {
        $this->secureStorageStatusMessage = '';
        if (empty($this->secureStorageKey)) {
            $this->secureStorageStatusMessage = 'Error: Key cannot be empty.';
            return;
        }

        $success = \App\Plugins\SecureStorage::delete($this->secureStorageKey);
        if ($success) {
            $this->secureStorageResult = '';
            $this->secureStorageStatusMessage = 'Value deleted successfully!';
        } else {
            $this->secureStorageStatusMessage = 'Error: Failed to delete value.';
        }
    }

    public function vibrateTap(): void
    {
        $this->vibrationStatusMessage = '';
        $ok = \App\Plugins\Vibration::vibrate(duration: 50, intensity: 0.6, sharpness: 0.7);
        $this->vibrationStatusMessage = $ok ? 'Tap feedback sent.' : 'Haptics unavailable or failed.';
    }

    public function vibrateSuccess(): void
    {
        $this->vibrationStatusMessage = '';
        $ok = \App\Plugins\Vibration::preset('success')->play();
        $this->vibrationStatusMessage = $ok ? 'Success pattern played.' : 'Haptics unavailable or failed.';
    }

    public function vibrateError(): void
    {
        $this->vibrationStatusMessage = '';
        $ok = \App\Plugins\Vibration::preset('error')->play();
        $this->vibrationStatusMessage = $ok ? 'Error pattern played.' : 'Haptics unavailable or failed.';
    }

    public function vibrateCancel(): void
    {
        $this->vibrationStatusMessage = '';
        $ok = Vibration::cancelVibration();
        $this->vibrationStatusMessage = $ok ? 'Vibration cancelled.' : 'Cancel failed or bridge unavailable.';
    }

    public function bgRefreshList(): void
    {
        $this->bgTaskStatusMessage = '';
        $this->bgTasks = BackgroundTasks::list();
        $this->bgTaskStatusMessage = count($this->bgTasks) === 0
            ? 'No background tasks registered.'
            : count($this->bgTasks).' task(s) loaded.';
    }

    public function bgCreate(): void
    {
        $this->bgTaskStatusMessage = '';
        $this->bgTaskOutput = '';

        $name = trim($this->bgTaskName);
        $command = trim($this->bgTaskCommand) !== '' ? trim($this->bgTaskCommand) : $name;

        if ($name === '') {
            $this->bgTaskStatusMessage = 'Error: Name is required.';

            return;
        }

        $task = BackgroundTasks::create([
            'name' => $name,
            'command' => $command,
            'intervalMinutes' => max(BackgroundTasks::MIN_INTERVAL_MINUTES, $this->bgTaskInterval),
            'enabled' => true,
            'longRunning' => false,
        ]);

        if ($task === null) {
            $this->bgTaskStatusMessage = 'Error: Failed to create task (native bridge unavailable?).';

            return;
        }

        $this->bgTaskId = (string) ($task['id'] ?? '');
        $this->bgTaskStatusMessage = 'Task created: '.($task['name'] ?? $name);
        $this->bgTasks = BackgroundTasks::list();
    }

    public function bgUpdate(): void
    {
        $this->bgTaskStatusMessage = '';
        $this->bgTaskOutput = '';

        if (trim($this->bgTaskId) === '') {
            $this->bgTaskStatusMessage = 'Error: Select or enter a task id to update.';

            return;
        }

        $attrs = [
            'intervalMinutes' => max(BackgroundTasks::MIN_INTERVAL_MINUTES, $this->bgTaskInterval),
            'enabled' => true,
        ];

        if (trim($this->bgTaskName) !== '') {
            $attrs['name'] = trim($this->bgTaskName);
        }
        if (trim($this->bgTaskCommand) !== '') {
            $attrs['command'] = trim($this->bgTaskCommand);
        }

        $task = BackgroundTasks::update($this->bgTaskId, $attrs);

        if ($task === null) {
            $this->bgTaskStatusMessage = 'Error: Update failed (task not found or bridge unavailable).';

            return;
        }

        $this->bgTaskStatusMessage = 'Task updated: '.($task['id'] ?? $this->bgTaskId);
        $this->bgTasks = BackgroundTasks::list();
    }

    public function bgSelect(string $id): void
    {
        $this->bgTaskOutput = '';
        $task = BackgroundTasks::get($id);

        if ($task === null) {
            $this->bgTaskStatusMessage = 'Error: Could not load task.';

            return;
        }

        $this->bgTaskId = (string) ($task['id'] ?? $id);
        $this->bgTaskName = (string) ($task['name'] ?? '');
        $this->bgTaskCommand = (string) ($task['command'] ?? $this->bgTaskName);
        $this->bgTaskInterval = max(
            BackgroundTasks::MIN_INTERVAL_MINUTES,
            (int) ($task['intervalMinutes'] ?? BackgroundTasks::MIN_INTERVAL_MINUTES)
        );
        $this->bgTaskStatusMessage = 'Loaded task '.$this->bgTaskId;
    }

    public function bgDelete(): void
    {
        $this->bgTaskStatusMessage = '';
        $this->bgTaskOutput = '';

        if (trim($this->bgTaskId) === '') {
            $this->bgTaskStatusMessage = 'Error: Enter a task id to delete.';

            return;
        }

        $ok = BackgroundTasks::delete($this->bgTaskId);
        if (! $ok) {
            $this->bgTaskStatusMessage = 'Error: Delete failed.';

            return;
        }

        $this->bgTaskStatusMessage = 'Task deleted: '.$this->bgTaskId;
        $this->bgTaskId = '';
        $this->bgTasks = BackgroundTasks::list();
    }

    public function bgSync(): void
    {
        $this->bgTaskStatusMessage = '';
        $this->bgTaskOutput = '';
        $ok = BackgroundTasks::sync();
        $this->bgTaskStatusMessage = $ok
            ? 'Tasks synced with OS scheduler.'
            : 'Error: Sync failed or bridge unavailable.';
        $this->bgTasks = BackgroundTasks::list();
    }

    public function bgRunNow(): void
    {
        $this->bgTaskStatusMessage = '';
        $this->bgTaskOutput = '';

        $id = trim($this->bgTaskId) !== '' ? trim($this->bgTaskId) : null;
        $result = BackgroundTasks::runNow($id);

        if (! ($result['success'] ?? false)) {
            $this->bgTaskStatusMessage = 'Error: '.($result['error'] ?? 'RunNow failed or bridge unavailable.');
            $this->bgTaskOutput = json_encode($result, JSON_PRETTY_PRINT) ?: '';

            return;
        }

        $results = $result['results'] ?? [];
        $this->bgTaskStatusMessage = is_array($results)
            ? 'RunNow completed ('.count($results).' result(s)).'
            : 'RunNow completed.';

        $lines = [];
        if (is_array($results)) {
            foreach ($results as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $lines[] = sprintf(
                    "[%s] %s\n%s",
                    $row['id'] ?? '?',
                    $row['command'] ?? '?',
                    trim((string) ($row['output'] ?? ''))
                );
            }
        }

        $this->bgTaskOutput = $lines !== []
            ? implode("\n\n---\n\n", $lines)
            : (json_encode($result, JSON_PRETTY_PRINT) ?: '');
    }

    private function logEvent(string $name, string $action, string $time, string $date): void
    {
        $event = [
            'name' => $name,
            'action' => $action,
            'time' => $time,
            'date' => $date,
            'lat' => $this->latitude,
            'lon' => $this->longitude,
        ];

        array_unshift($this->history, $event);
        if (count($this->history) > 5) {
            array_pop($this->history);
        }
    }
};