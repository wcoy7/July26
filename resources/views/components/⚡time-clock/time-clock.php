<?php

use App\Plugins\BackgroundTasks;
use App\Plugins\Geolocation;
use App\Plugins\LocalNotifications;
use App\Plugins\Vibration;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Component;
use Native\Mobile\Facades\Dialog;

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

    /** Dismissible banner on the main time-clock screen (outside the modal). */
    public string $bgTaskBanner = '';

    /** @var list<array<string, mixed>> */
    public array $bgTasks = [];

    // Local notifications test modal
    public string $notifyTitle = 'Time Portal';

    public string $notifyBody = 'Hello from LocalNotifications!';

    public int $notifyDelaySeconds = 5;

    public string $notifyStatusMessage = '';

    public string $notifyLastId = '';

    public bool $notifyHasPermission = false;

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
            $message = 'Error: '.($result['error'] ?? 'RunNow failed or bridge unavailable.');
            $this->bgTaskStatusMessage = $message;
            $this->bgTaskOutput = json_encode($result, JSON_PRETTY_PRINT) ?: '';
            $this->notifyBackgroundTask($message, success: false);

            return;
        }

        $results = $result['results'] ?? [];
        $this->bgTaskStatusMessage = is_array($results)
            ? 'RunNow completed ('.count($results).' result(s)).'
            : 'RunNow completed.';

        $lines = [];
        $firstSnippet = '';
        if (is_array($results)) {
            foreach ($results as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $output = trim((string) ($row['output'] ?? ''));
                $lines[] = sprintf(
                    "[%s] %s\n%s",
                    $row['id'] ?? '?',
                    $row['command'] ?? '?',
                    $output
                );
                if ($firstSnippet === '' && $output !== '') {
                    $firstSnippet = $output;
                }
            }
        }

        $this->bgTaskOutput = $lines !== []
            ? implode("\n\n---\n\n", $lines)
            : (json_encode($result, JSON_PRETTY_PRINT) ?: '');

        $banner = $this->bgTaskStatusMessage;
        if ($firstSnippet !== '') {
            $banner .= ' · '.Str::limit(preg_replace('/\s+/', ' ', $firstSnippet) ?? $firstSnippet, 120);
        }

        $this->notifyBackgroundTask($banner, success: true, detail: $this->bgTaskOutput);

        // System tray notification (also fired natively after each task run)
        if (! LocalNotifications::hasPermission()) {
            LocalNotifications::requestPermission();
        }

        $notif = LocalNotifications::show(
            'Background task finished',
            Str::limit($banner, 160),
            'bg_task_'.($id ?? 'all').'_'.time()
        );

        if ($notif['success'] ?? false) {
            $this->bgTaskStatusMessage .= ' · Notification sent (check banner / Notification Center).';
        } else {
            $this->bgTaskStatusMessage .= ' · Notification failed: '.($notif['error'] ?? 'unknown — open NOTIFY → Allow first.');
        }
    }

    public function dismissBgTaskBanner(): void
    {
        $this->bgTaskBanner = '';
    }

    public function notifyRefreshPermission(): void
    {
        $this->notifyHasPermission = LocalNotifications::hasPermission();
        $this->notifyStatusMessage = $this->notifyHasPermission
            ? 'Notifications permission granted.'
            : 'Notifications not permitted yet — tap Allow.';
    }

    public function notifyRequestPermission(): void
    {
        $granted = LocalNotifications::requestPermission();
        // Re-check after the system prompt (may still be false until the user answers)
        $this->notifyHasPermission = LocalNotifications::hasPermission() || $granted;
        $this->notifyStatusMessage = $this->notifyHasPermission
            ? 'Permission granted. Tap “Show now” to post a notification (Allow alone does not send one).'
            : 'If you saw a system prompt, accept it, then tap Check permission. Allow does not send a notification by itself.';
    }

    public function notifyShowNow(): void
    {
        // Ensure permission first when possible
        if (! LocalNotifications::hasPermission()) {
            LocalNotifications::requestPermission();
            $this->notifyHasPermission = LocalNotifications::hasPermission();
        }

        $title = trim($this->notifyTitle) !== '' ? trim($this->notifyTitle) : 'Notification';
        $body = trim($this->notifyBody) !== '' ? trim($this->notifyBody) : 'Hello!';
        $result = LocalNotifications::show($title, $body);

        if ($result['success'] ?? false) {
            $this->notifyLastId = (string) ($result['id'] ?? '');
            $this->notifyStatusMessage = 'Notification posted (id: '.$this->notifyLastId.'). Look for a banner at the top, or open Notification Center. Swipe to dismiss.';
            Flux::toast(variant: 'success', text: 'Notification posted — check the top of the screen / Notification Center.');
        } else {
            $error = (string) ($result['error'] ?? 'Failed to show notification.');
            $this->notifyStatusMessage = 'Error: '.$error.' Tip: tap Allow first, then Show now. On iOS, banners also need a rebuild that includes the latest native code.';
            Flux::toast(variant: 'danger', text: $this->notifyStatusMessage);
        }
    }

    public function notifySchedule(): void
    {
        $title = trim($this->notifyTitle) !== '' ? trim($this->notifyTitle) : 'Scheduled notification';
        $body = trim($this->notifyBody) !== '' ? trim($this->notifyBody) : 'This is a delayed notification.';
        $delay = max(1, $this->notifyDelaySeconds);
        $result = LocalNotifications::schedule($title, $body, $delay);

        if ($result['success'] ?? false) {
            $this->notifyLastId = (string) ($result['id'] ?? '');
            $this->notifyStatusMessage = "Scheduled in {$delay}s (id: {$this->notifyLastId}).";
            Flux::toast(variant: 'success', text: "Notification scheduled in {$delay}s.");
        } else {
            $this->notifyStatusMessage = 'Error: '.($result['error'] ?? 'Failed to schedule.');
            Flux::toast(variant: 'danger', text: $this->notifyStatusMessage);
        }
    }

    public function notifyCancelLast(): void
    {
        if ($this->notifyLastId === '') {
            $this->notifyStatusMessage = 'Error: No notification id to cancel.';

            return;
        }

        $ok = LocalNotifications::cancel($this->notifyLastId);
        $this->notifyStatusMessage = $ok
            ? 'Cancelled: '.$this->notifyLastId
            : 'Error: Cancel failed.';
    }

    public function notifyCancelAll(): void
    {
        $ok = LocalNotifications::cancelAll();
        $this->notifyStatusMessage = $ok ? 'All local notifications cancelled.' : 'Error: Cancel all failed.';
        $this->notifyLastId = '';
    }

    /**
     * Show a dismissible on-screen banner, Flux toast, and native alert (when available).
     */
    private function notifyBackgroundTask(string $message, bool $success, string $detail = ''): void
    {
        $this->bgTaskBanner = $message;

        Flux::toast(
            variant: $success ? 'success' : 'danger',
            text: $message,
            duration: 5000,
        );

        // Native dismissible alert (OK button) when running inside NativePHP
        if (function_exists('nativephp_call')) {
            $body = $detail !== ''
                ? $message."\n\n".Str::limit($detail, 400)
                : $message;

            try {
                Dialog::alert(
                    $success ? 'Background task finished' : 'Background task failed',
                    $body,
                    ['OK']
                )->show();
            } catch (\Throwable) {
                // Dialog bridge may not be registered; banner + toast still show.
            }
        }
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