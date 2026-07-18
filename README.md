# Valley Inventory Service - Employee Time Portal

A modern, native-compatible mobile application designed as an Employee Time Portal for Valley Inventory Service. The application tracks employee clock-in/out activities, breaks, and logs GPS coordinates using Leaflet and NativePHP Geolocation.

---

## 🚀 High-Level Summary
The **Employee Time Portal** provides a secure, streamlined interface for staff members to manage their daily shifts:
- **PIN Verification**: Employees authenticate using a secure 4-digit PIN via an interactive on-screen keypad.
- **Dynamic Digital Clock**: Features a real-time, responsive clock showing current local time and date.
- **Shift & Break Logging**: Allows employees to transition between states (Clock In, Start Break, End Break, Clock Out) with automated validation and break-ending logic.
- **GPS Location Tracking**: Pins employee geolocation coordinates during punch events and displays their location on an interactive OpenStreetMap Leaflet map.
- **Secure Storage (Keychain)**: Demo UI on the time-clock screen to save, retrieve, and delete sensitive key/value pairs via the device secure store (iOS Keychain / Android EncryptedSharedPreferences).
- **Haptic Feedback (Vibration)**: Demo UI to trigger single taps and preset patterns via a custom Vibration plugin (iOS Core Haptics / Android VibrationEffect), inspired by [jvdluk/pro-vibration](https://nativephp.com/plugins/jvdluk/pro-vibration).
- **Background Tasks**: Custom on-device task registry with full CRUD (create/list/get/update/delete), inspired by [nativephp/mobile-background-tasks](https://nativephp.com/plugins/nativephp/mobile-background-tasks).
- **Offline / Native Ready**: Designed to run as a mobile app utilizing NativePHP Mobile with offline fallbacks.

---

## 📦 Packages Used

### PHP / Laravel Packages (`composer.json`)
- **`nativephp/mobile`**: Core framework for compiling and packaging the Laravel application as a native iOS/Android mobile app.
- **`livewire/livewire` (v4)**: Reactivity engine enabling dynamic UI updates without write-ups in heavy frontend JS frameworks.
- **`livewire/flux` & `livewire/flux-pro`**: Tailwind UI component library providing elegant layout, input elements, buttons, and status badges.
- **`livewire/blaze`**: Optimizes and accelerates rendering for blade-component-heavy layouts.
- **`laravel/fortify`**: Frontend-agnostic authentication backend for managing user accounts, passwords, and security.
- **`laravel/chisel`**: Laravel starter-kit/scaffolding tool.
- **`laravel/tinker`**: Interactive shell utility for debugging the Laravel application in real-time.

### NPM / Frontend Packages (`package.json`)
- **`tailwindcss` (v4)**: Modern CSS utility framework.
- **`@tailwindcss/vite`**: Custom integration package for compiling Tailwind V4 classes through Vite.
- **`laravel-vite-plugin`**: Connects Vite with the Laravel backend for asset compilation.
- **`@laravel/passkeys`**: Helper scripts for secure passkey/WebAuthn authentication.
- **`vite`**: The asset bundler and development server.
- **`concurrently`**: Command-line runner for managing Laravel and Vite services concurrently.

---

## 🛠 Custom Modules / Plugins Created

### `App\Plugins\Geolocation`
Custom PHP wrapper over the NativePHP bridge. Invokes `Geolocation.GetLocation`, normalizes success/error payloads, and supports offline fallbacks when the native bridge is unavailable.

### `App\Plugins\SecureStorage`
Custom PHP API for secure key/value storage via the native bridge:

| Method | Bridge call | Returns |
|--------|-------------|---------|
| `SecureStorage::set($key, $value)` | `SecureStorage.Set` | `bool` |
| `SecureStorage::get($key)` | `SecureStorage.Get` | `?string` |
| `SecureStorage::delete($key)` | `SecureStorage.Delete` | `bool` |

Native implementations (registered on the bridge):

- **iOS** (`nativephp/ios/NativePHP/Bridge/Plugins/SecureStoragePlugin.swift`): Keychain (`kSecClassGenericPassword`), accessible when unlocked on this device only.
- **Android** (`nativephp/android/.../SecureStorageFunctions.kt`): `EncryptedSharedPreferences` (AES-256 via AndroidX Security Crypto).
- **Registration**: `BridgeFunctionRegistration.swift` / `BridgeFunctionRegistration.kt` wire `SecureStorage.Set|Get|Delete` into the NativePHP bridge registry.

### `App\Plugins\Vibration`
Custom haptic API (inspired by [jvdluk/pro-vibration](https://nativephp.com/plugins/jvdluk/pro-vibration)):

| Method | Bridge call | Returns |
|--------|-------------|---------|
| `Vibration::vibrate($duration, $intensity, $sharpness?)` | `Vibration.Vibrate` | `bool` |
| `Vibration::hasHaptics()` | `Vibration.HasHaptics` | `bool` |
| `Vibration::cancelVibration()` | `Vibration.Cancel` | `bool` |
| `Vibration::pattern($steps = [])` | builder → `Vibration.PlayPattern` | `VibrationPatternBuilder` |
| `Vibration::preset($name)` | builder with preset steps | `VibrationPatternBuilder` |

Presets: `success`, `error`, `warning`, `notification`, `double_click`.

Native implementations:

- **iOS** (`VibrationPlugin.swift`): Core Haptics (`CHHapticEngine`) with intensity + sharpness.
- **Android** (`VibrationFunctions.kt`): `VibrationEffect` one-shot / waveform; intensity mapped to amplitude. Sharpness ignored. Requires `VIBRATE` permission.
- **Registration**: `BridgeFunctionRegistration.swift` / `.kt`.

### `App\Plugins\BackgroundTasks`
Custom background-task registry inspired by [nativephp/mobile-background-tasks](https://nativephp.com/plugins/nativephp/mobile-background-tasks). Tasks are stored on-device and managed via CRUD bridge calls (intervals clamped to a **15-minute** minimum, matching mobile scheduler limits).

| Method | Bridge call | Returns |
|--------|-------------|---------|
| `BackgroundTasks::create($attributes)` | `BackgroundTasks.Create` | `?array` task |
| `BackgroundTasks::get($id)` | `BackgroundTasks.Get` | `?array` task |
| `BackgroundTasks::list()` | `BackgroundTasks.List` | `list<array>` |
| `BackgroundTasks::update($id, $attributes)` | `BackgroundTasks.Update` | `?array` task |
| `BackgroundTasks::delete($id)` | `BackgroundTasks.Delete` | `bool` |
| `BackgroundTasks::sync()` | `BackgroundTasks.Sync` | `bool` — re-register with OS |
| `BackgroundTasks::runNow($id = null)` | `BackgroundTasks.RunNow` | `array` — run immediately (dev/test) |

Task shape:

```php
[
    'id' => 'uuid',
    'name' => 'sync:data',
    'command' => 'sync:data',
    'intervalMinutes' => 15,
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
    'createdAt' => '...',
    'updatedAt' => '...',
]
```

Example:

```php
use App\Plugins\BackgroundTasks;

$task = BackgroundTasks::create([
    'name' => 'sync:data',
    'intervalMinutes' => 15,
    'constraints' => ['onWifi' => true, 'whileCharging' => true],
]);

BackgroundTasks::update($task['id'], ['enabled' => false]);
BackgroundTasks::list();
BackgroundTasks::sync(); // re-register with OS
BackgroundTasks::runNow(); // force-run all enabled tasks now
BackgroundTasks::delete($task['id']);
```

Native implementations:

- **iOS** (`BackgroundTasksPlugin.swift` + `BackgroundTasksScheduler`):
  - JSON registry in `UserDefaults`
  - Schedules `BGAppRefreshTask` / `BGProcessingTask` (`UIBackgroundModes` fetch+processing)
  - On fire: boots PHP if needed and runs artisan `command` via `PersistentPHPRuntime`
- **Android** (`BackgroundTasksFunctions.kt`, `BackgroundTasksScheduler.kt`, `BackgroundTaskWorker.kt`):
  - JSON registry in `SharedPreferences`
  - **WorkManager** periodic work per enabled task (constraints mapped to WorkManager constraints)
  - On fire: `LaravelEnvironment.initializeForBackground()` + ephemeral PHP artisan
  - Boot receiver re-schedules after reboot / app update
- **Registration**: `BackgroundTasks.Create|Get|List|Update|Delete|Sync|RunNow`

**Platform notes (same as official plugin):** Android WorkManager min interval **15 minutes**. iOS BGTaskScheduler timing is best-effort (OS discretionary). Constraints like battery/storage/idle are Android-first; iOS maps network + charging onto processing tasks.

### `⚡time-clock` (Livewire Component)
- **Controller (`time-clock.php`)**: Manages employee state transitions, validates PIN codes, updates shift history, refreshes GPS, Secure Storage actions, and haptic demo actions (`vibrateTap`, `vibrateSuccess`, `vibrateError`, `vibrateCancel`).
- **View (`time-clock.blade.php`)**: AlpineJS digital clock, PIN keypad, shift actions, Leaflet map (`wire:ignore`), status feedback, **HAPTIC** and **KEYCHAIN** Flux modals.

---

## 🧪 Tests
- **`tests/Feature/SecureStorageTest.php`**: Unit-style feature coverage for set/get/delete (including mocked `nativephp_call` success/failure paths).
- **`tests/Feature/VibrationTest.php`**: Vibration vibrate/hasHaptics/cancel/pattern/preset coverage with mocked bridge calls.
- **`tests/Feature/BackgroundTasksTest.php`**: Background task CRUD, interval clamping, `sync()`, `runNow()`, and missing-bridge handling.
- **`tests/Feature/TimeClockTest.php`**: Time-clock UI and behavior tests, including Secure Storage and Haptic modal markup and Livewire actions.

---

## 📱 Running on device / simulator
```bash
# Frontend assets for iOS
npm run build -- --mode=ios

# Simulator or connected iPhone (device picker / UDID)
php artisan native:run ios
# e.g. php artisan native:run ios 00008120-000C30A61A3B401E
```

**Notes:**
- Set `NATIVEPHP_APP_ID` and `NATIVEPHP_DEVELOPMENT_TEAM` in `.env` for physical device builds.
- Free Apple Developer accounts are limited to **3** development apps installed per device.
- Prefer keeping the project outside iCloud-synced folders (e.g. not `~/Documents` with Desktop & Documents iCloud) to avoid codesign “resource fork / Finder information” failures.
