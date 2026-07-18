<div class="w-full max-w-md mx-auto flex flex-col gap-5">
    <div class="text-center space-y-1">
        <flux:heading size="xl">Haptic Lab</flux:heading>
        <flux:text size="sm" class="text-zinc-500">
            Test vibration intensity, duration, sharpness, and presets
        </flux:text>
    </div>

    <div class="flex items-center justify-center gap-2">
        @if ($supportsHaptics)
            <flux:badge color="lime" size="sm">Haptics supported</flux:badge>
        @else
            <flux:badge color="zinc" size="sm">Haptics unavailable</flux:badge>
        @endif
        <flux:badge color="sky" size="sm" icon="bolt">
            {{ number_format($intensity * 100, 0) }}% · {{ $duration }}ms
        </flux:badge>
    </div>

    @if ($statusMessage)
        <flux:callout icon="information-circle" color="blue">
            <flux:callout.text>{{ $statusMessage }}</flux:callout.text>
        </flux:callout>
    @endif

    {{-- Intensity levels --}}
    <section class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 space-y-3 shadow-sm">
        <div class="flex items-center justify-between">
            <flux:heading size="sm">Intensity levels</flux:heading>
            <flux:text size="sm" class="text-zinc-500">{{ number_format($intensity * 100, 0) }}%</flux:text>
        </div>
        <div class="grid grid-cols-1 gap-2">
            @foreach ($intensityLevels as $level)
                <flux:button
                    wire:key="intensity-{{ $level['intensity'] }}"
                    wire:click="vibrateAtLevel({{ $level['intensity'] }})"
                    variant="{{ abs($intensity - $level['intensity']) < 0.001 ? 'primary' : 'outline' }}"
                    class="w-full justify-between"
                    icon:trailing="bolt"
                >
                    <span>{{ $level['label'] }}</span>
                    <span class="text-xs opacity-70">{{ number_format($level['intensity'] * 100, 0) }}%</span>
                </flux:button>
            @endforeach
        </div>
    </section>

    {{-- Duration --}}
    <section class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 space-y-3 shadow-sm">
        <div class="flex items-center justify-between">
            <flux:heading size="sm">Duration</flux:heading>
            <flux:text size="sm" class="text-zinc-500">{{ $duration }}ms</flux:text>
        </div>
        <div class="grid grid-cols-5 gap-2">
            @foreach ($durationLevels as $level)
                <flux:button
                    wire:key="duration-{{ $level['duration'] }}"
                    wire:click="vibrateDuration({{ $level['duration'] }})"
                    size="sm"
                    variant="{{ $duration === $level['duration'] ? 'primary' : 'outline' }}"
                    class="w-full"
                >
                    {{ $level['label'] }}
                </flux:button>
            @endforeach
        </div>
    </section>

    {{-- Custom controls --}}
    <section class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 space-y-4 shadow-sm">
        <flux:heading size="sm">Custom</flux:heading>

        <flux:field>
            <flux:label>Intensity ({{ number_format($intensity * 100, 0) }}%)</flux:label>
            <flux:slider wire:model.live="intensity" min="0" max="1" step="0.05" />
        </flux:field>

        <flux:field>
            <flux:label>Duration ({{ $duration }}ms)</flux:label>
            <flux:slider wire:model.live="duration" min="10" max="1000" step="10" />
        </flux:field>

        <flux:field>
            <flux:label>Sharpness (iOS) · {{ number_format($sharpness * 100, 0) }}%</flux:label>
            <flux:slider wire:model.live="sharpness" min="0" max="1" step="0.05" />
            <flux:description>Ignored on Android.</flux:description>
        </flux:field>

        <div class="grid grid-cols-3 gap-2">
            <flux:button wire:click="setSharpness(0.1)" size="sm" variant="outline">Soft</flux:button>
            <flux:button wire:click="setSharpness(0.5)" size="sm" variant="outline">Medium</flux:button>
            <flux:button wire:click="setSharpness(0.9)" size="sm" variant="outline">Sharp</flux:button>
        </div>

        <flux:button wire:click="playCustom" variant="primary" class="w-full" icon="play">
            Play custom
        </flux:button>
    </section>

    {{-- Presets --}}
    <section class="rounded-2xl border border-zinc-200/80 dark:border-zinc-800 bg-white dark:bg-zinc-900 p-4 space-y-3 shadow-sm">
        <flux:heading size="sm">Presets</flux:heading>
        <div class="grid grid-cols-2 gap-2">
            @foreach ($this->presetNames() as $preset)
                <flux:button
                    wire:key="preset-{{ $preset }}"
                    wire:click="playPreset('{{ $preset }}')"
                    variant="outline"
                    class="w-full capitalize"
                >
                    {{ str_replace('_', ' ', $preset) }}
                </flux:button>
            @endforeach
        </div>
        <flux:button wire:click="playPatternDemo" variant="filled" class="w-full" icon="heart">
            Heartbeat pattern
        </flux:button>
    </section>

    <flux:button wire:click="cancel" variant="danger" class="w-full" icon="x-mark">
        Cancel vibration
    </flux:button>
</div>
