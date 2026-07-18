# Valley Inventory Service - Employee Time Portal

A modern **NativePHP Mobile** app for Valley Inventory Service employees: PIN-based time clock, GPS punches, secure keychain storage, haptics — and a full **on-device background task system** that schedules and runs Laravel artisan commands even when the app is in the background.

Built with Laravel, Livewire, Flux UI, and custom native plugins for iOS and Android.

---

## 🚀 High-Level Summary

### Core time portal
- **PIN Verification** — 4-digit on-screen keypad with strong 100ms haptic feedback per key
- **Dynamic Digital Clock** — Live local time and date
- **Shift & Break Logging** — Clock In / Start Break / End Break / Clock Out
- **GPS Location Tracking** — Leaflet map when a live fix is available; cached coordinates used for punches when GPS is unreachable
- **Secure Storage (Keychain)** — KEYCHAIN modal for set/get/delete via iOS Keychain / Android EncryptedSharedPreferences
- **Haptic Feedback** — Vibration plugin + optional Haptic Lab tester component
- **Barcode / QR Scanner** — SCAN modal opens a native camera scanner (AVFoundation / ML Kit)

### ⭐ Background Tasks (custom native plugin)
A first-class **background job runner** inspired by [nativephp/mobile-background-tasks](https://nativephp.com/plugins/nativephp/mobile-background-tasks):

| Capability | What it does |
|------------|----------------|
| **CRUD registry** | Create, list, get, update, delete task definitions on-device |
| **OS scheduling** | Android **WorkManager** · iOS **BGTaskScheduler** |
| **Artisan execution** | Runs stored Laravel `command`s when the OS fires the job |
| **Constraints** | Wi‑Fi / any network / charging / battery / storage / idle |
| **Long-running** | iOS `BGProcessingTask` path for heavier work |
| **Sync / RunNow** | Re-register with the OS, or force-run immediately for testing |

Tasks keep running on OS schedules after the user leaves the app (subject to platform rules — see notes below).

---

## 📦 Packages Used

### PHP / Laravel (`composer.json`)
- **`nativephp/mobile`** — Native iOS/Android shell + PHP runtime
- **`livewire/livewire` (v4)** — Reactive UI
- **`livewire/flux` & `livewire/flux-pro`** — UI components
- **`livewire/blaze`** — Blade render optimization
- **`laravel/fortify`** — Auth backend
- **`laravel/chisel`** · **`laravel/tinker`**

### Frontend (`package.json`)
- **`tailwindcss` (v4)** · **`@tailwindcss/vite`** · **`laravel-vite-plugin`** · **`vite`** · **`concurrently`** · **`@laravel/passkeys`**

---

## 🛠 Custom Native Plugins

### `App\Plugins\BackgroundTasks` ⭐

Full background-task system: on-device registry **plus** real OS scheduling and artisan execution.

#### Architecture

```text
PHP (BackgroundTasks::create/update/…)
        │
        ▼
  Native bridge (Create|Update|Delete|Sync|RunNow)
        │
        ├─► Persist task JSON (UserDefaults / SharedPreferences)
        │
        └─► Schedule with OS
              Android: WorkManager periodic work (per task)
              iOS:     BGAppRefreshTask / BGProcessingTask
                        │
                        ▼ when OS fires
              Boot PHP if needed → artisan <command> → done
```

#### API

| Method | Bridge | Returns |
|--------|--------|---------|
| `create($attributes)` | `BackgroundTasks.Create` | `?array` task |
| `get($id)` | `BackgroundTasks.Get` | `?array` task |
| `list()` | `BackgroundTasks.List` | list of tasks |
| `update($id, $attributes)` | `BackgroundTasks.Update` | `?array` task |
| `delete($id)` | `BackgroundTasks.Delete` | `bool` |
| `sync()` | `BackgroundTasks.Sync` | `bool` — re-register all with OS |
| `runNow($id = null)` | `BackgroundTasks.RunNow` | `array` — force run (dev/test) |

#### Task shape

```php
[
    'id' => 'uuid',
    'name' => 'sync:data',
    'command' => 'inspire',           // artisan command string
    'intervalMinutes' => 15,          // min 15 (mobile limit)
    'enabled' => true,
    'longRunning' => false,
    'constraints' => [
        'onAnyNetwork' => false,
        'onWifi' => false,
        'whileCharging' => false,
        'whenBatteryNotLow' => false,
        'whenStorageNotLow' => false,
        'whenIdle' => false,
    ],
    'createdAt' => '…',
    'updatedAt' => '…',
]
```

#### Example usage

```php
use App\Plugins\BackgroundTasks;

// Register a recurring background artisan job
$task = BackgroundTasks::create([
    'name' => 'hourly-inspire',
    'command' => 'inspire',
    'intervalMinutes' => 60,
    'constraints' => [
        'onAnyNetwork' => true,
    ],
]);

// Manage lifecycle
BackgroundTasks::list();
BackgroundTasks::update($task['id'], ['enabled' => true]);
BackgroundTasks::sync();              // re-push schedules to the OS
BackgroundTasks::runNow($task['id']); // test immediately (bypass interval)
BackgroundTasks::delete($task['id']);
```

#### Native implementation details

| Platform | Storage | Scheduler | Execution |
|----------|---------|-----------|-----------|
| **iOS** | `UserDefaults` JSON | `BGAppRefreshTask` (quick) · `BGProcessingTask` (longRunning / constrained) | `PersistentPHPRuntime::artisan()` (boots PHP on cold start if needed) |
| **Android** | `SharedPreferences` JSON | **WorkManager** unique periodic work per task | `LaravelEnvironment.initializeForBackground()` + **ephemeral** PHP artisan |

Also includes:

- **iOS** `Info.plist`: `UIBackgroundModes` (`fetch`, `processing`) + `BGTaskSchedulerPermittedIdentifiers`
- **Android** WorkManager dependency, boot/`MY_PACKAGE_REPLACED` receiver to re-schedule, `RECEIVE_BOOT_COMPLETED` + `WAKE_LOCK`
- Bridge bootstrap on app ready / activity start so schedules survive relaunch

#### Platform notes

- **Android** WorkManager enforces a **15-minute** minimum interval (shorter values are clamped).
- **iOS** BGTaskScheduler timing is **best-effort** (usage patterns, battery, Background App Refresh). Treat as “should run,” not “will run at exact times.”
- **`runNow()`** is the reliable way to verify artisan execution during development.
- iOS force-fire (Xcode LLDB, app backgrounded):

```text
e -l objc -- (void)[[BGTaskScheduler sharedScheduler] _simulateLaunchForTaskWithIdentifier:@"com.nativephp.background-tasks.refresh"]
```

Inspired by the official [nativephp/mobile-background-tasks](https://nativephp.com/plugins/nativephp/mobile-background-tasks) plugin, implemented as a **custom in-app plugin** for this project.

---

### `App\Plugins\SecureStorage`

| Method | Bridge | Returns |
|--------|--------|---------|
| `set($key, $value)` | `SecureStorage.Set` | `bool` |
| `get($key)` | `SecureStorage.Get` | `?string` |
| `delete($key)` | `SecureStorage.Delete` | `bool` |

- **iOS** Keychain · **Android** EncryptedSharedPreferences

### `App\Plugins\Vibration`

| Method | Bridge | Returns |
|--------|--------|---------|
| `vibrate($duration, $intensity, $sharpness?)` | `Vibration.Vibrate` | `bool` |
| `hasHaptics()` | `Vibration.HasHaptics` | `bool` |
| `cancelVibration()` | `Vibration.Cancel` | `bool` |
| `pattern()` / `preset()` | `Vibration.PlayPattern` | builder / `bool` |

Presets: `success`, `error`, `warning`, `notification`, `double_click`.  
**iOS** Core Haptics · **Android** VibrationEffect.

### `App\Plugins\Geolocation`

`getCurrentPosition()` via `Geolocation.GetLocation`, with normalized errors and offline fallbacks.

### `App\Plugins\LocalNotifications`

System **notification center / tray** notifications (user can swipe to dismiss). Local only — no FCM/APNs server.

| Method | Bridge | Returns |
|--------|--------|---------|
| `requestPermission()` | `LocalNotifications.RequestPermission` | `bool` |
| `hasPermission()` | `LocalNotifications.HasPermission` | `bool` |
| `show($title, $body, $id?)` | `LocalNotifications.Show` | `array` |
| `schedule($title, $body, $delaySeconds, $id?)` | `LocalNotifications.Schedule` | `array` |
| `cancel($id)` | `LocalNotifications.Cancel` | `bool` |
| `cancelAll()` | `LocalNotifications.CancelAll` | `bool` |

```php
use App\Plugins\LocalNotifications;

LocalNotifications::requestPermission();
LocalNotifications::show('Shift reminder', 'Time to clock in!');
LocalNotifications::schedule('Break over', 'Please return to work.', 60);
```

- **iOS**: `UserNotifications` (`UNUserNotificationCenter`)
- **Android**: `NotificationCompat` + channel + `AlarmManager` for scheduled
- Time-clock toolbar: **NOTIFY** modal for manual testing

### `App\Plugins\Scanner` 📷

Barcode and QR scanning inspired by [nativephp/mobile-scanner](https://nativephp.com/plugins/nativephp/mobile-scanner), implemented as a **custom in-app plugin** (no paid package required).

#### Architecture

```text
PHP  Scanner::scan()->prompt()->formats()->id()
        │
        ▼
  Bridge  Scanner.Scan
        │
        ├─ iOS: AVFoundation full-screen UI → LaravelBridge CodeScanned
        └─ Android: CameraX + ML Kit ScannerActivity → CodeScanned via MainActivity WebView
```

#### API

| Method | Bridge | Notes |
|--------|--------|-------|
| `Scanner::scan()` | — | Returns fluent `PendingBarcodeScan` |
| `->prompt(string)` | — | Overlay text on scanner UI |
| `->continuous(bool)` | — | Keep open for multi-scan (default `false`) |
| `->formats(array)` | — | `qr`, `ean13`, `ean8`, `code128`, `code39`, `upca`, `upce`, `all` |
| `->id(string)` | — | Session id for multi-context handling |
| `->scan()` / `__destruct()` | `Scanner.Scan` | Opens native camera UI |

Results are **async** via `Native\Mobile\Events\Scanner\CodeScanned` (`data`, `format`, `id`).

```php
use App\Plugins\Scanner;
use Native\Mobile\Attributes\OnNative;
use Native\Mobile\Events\Scanner\CodeScanned;

// Open scanner (auto-starts on destruct, or call ->scan())
Scanner::scan()
    ->prompt('Scan product barcode')
    ->continuous(false)
    ->formats(['qr', 'ean13', 'code128'])
    ->id('checkout');

#[OnNative(CodeScanned::class)]
public function handleScan(string $data, string $format, ?string $id = null): void
{
    // $data = decoded value, $format = qr|ean13|…
}
```

- **iOS**: AVFoundation metadata scanning · `NSCameraUsageDescription` in Info.plist  
- **Android**: CameraX + ML Kit barcode-scanning · `CAMERA` permission · `ScannerActivity`  
- Time-clock toolbar: **SCAN** modal for manual testing

---

## 🖥 Livewire UI

### `⚡time-clock` (default home `/`)
PIN keypad (haptic keys), digital clock, shift actions, optional GPS map, **KEYCHAIN**, **HAPTIC**, **NOTIFY**, **TASKS**, and **SCAN** modals.

### `⚡vibration-tester` (Haptic Lab)
Optional component for intensity/duration/preset experiments (`<livewire:vibration-tester />`).

---

## 🧪 Tests

| File | Coverage |
|------|----------|
| `tests/Feature/BackgroundTasksTest.php` | CRUD, interval clamp, `sync()`, `runNow()` |
| `tests/Feature/SecureStorageTest.php` | set/get/delete bridge mocks |
| `tests/Feature/VibrationTest.php` | vibrate / patterns / presets |
| `tests/Feature/VibrationTesterTest.php` | Haptic Lab UI actions |
| `tests/Feature/LocalNotificationsTest.php` | permission / show / schedule / cancel |
| `tests/Feature/ScannerTest.php` | fluent API, defaults, once-only, auto-start |
| `tests/Feature/TimeClockTest.php` | home route, PIN/shifts, haptics, SCAN modal |

```bash
php artisan test --compact \
  tests/Feature/BackgroundTasksTest.php \
  tests/Feature/SecureStorageTest.php \
  tests/Feature/VibrationTest.php \
  tests/Feature/ScannerTest.php \
  tests/Feature/TimeClockTest.php
```

---

## 📱 Run on device / simulator

```bash
npm run build -- --mode=ios   # or --mode=android
php artisan native:run ios    # or android
# e.g. php artisan native:run ios 00008120-000C30A61A3B401E
```

**Notes**
- Set `NATIVEPHP_APP_ID` and `NATIVEPHP_DEVELOPMENT_TEAM` in `.env` for physical devices.
- Free Apple Developer accounts: max **3** development apps per device.
- Prefer projects outside iCloud-synced Documents to avoid codesign xattr issues.
- After adding background tasks, open the app once so schedules register; use `BackgroundTasks::runNow()` to verify execution quickly.
