# Valley Inventory Service - Employee Time Portal

A modern, native-compatible mobile application designed as an Employee Time Portal for Valley Inventory Service. The application tracks employee clock-in/out activities, breaks, and logs GPS coordinates using Leaflet and NativePHP Geolocation.

---

## 🚀 High-Level Summary
The **Employee Time Portal** provides a secure, streamlined interface for staff members to manage their daily shifts:
- **PIN Verification**: Employees authenticate using a secure 4-digit PIN via an interactive on-screen keypad.
- **Dynamic Digital Clock**: Features a real-time, responsive clock showing current local time and date.
- **Shift & Break Logging**: Allows employees to transition between states (Clock In, Start Break, End Break, Clock Out) with automated validation and break-ending logic.
- **GPS Location Tracking**: Pins employee geolocation coordinates during punch events and displays their location on an interactive OpenStreetMap Leaflet map.
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
- **`App\Plugins\Geolocation`**: A custom wrapper class that integrates with the NativePHP bridge. It invokes `Geolocation.GetLocation`, handles status outputs, and provides normalized error catching and fallback coordinate options.
- **`⚡time-clock` (Livewire Component)**:
  - **Controller (`time-clock.php`)**: Manages employee state transitions, validates user PIN codes, updates shift history arrays, and refreshes GPS geolocation data.
  - **View (`time-clock.blade.php`)**: Features the AlpineJS-driven digital clock, the PIN visualizer, shift action states, a Leaflet-backed map element (safeguarded via `wire:ignore` from re-rendering glitches), and temporary action feedback notifications.
