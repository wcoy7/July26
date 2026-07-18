<div x-data="{
         validated: @entangle('validatedEmployee'),
         pin: '',
         haptic() {
             // Strong 100ms feedback via native bridge (non-blocking Livewire call)
             this.$wire.vibrateKeypad();
         },
         addDigit(digit) {
             this.haptic();
             if (this.pin.length < 4) {
                 this.pin += digit;
                 if (this.pin.length === 4) {
                     this.submit();
                 }
             }
         },
         clear() {
             this.haptic();
             this.pin = '';
         },
         backspace() {
             this.haptic();
             this.pin = this.pin.slice(0, -1);
         },
         submit() {
             if (this.pin.length === 4) {
                 this.$wire.validatePin(this.pin);
                 setTimeout(() => { this.pin = ''; }, 1000);
             }
         }
     }"
     class="w-full max-w-md bg-white/70 dark:bg-zinc-900/70 border border-zinc-200/50 dark:border-zinc-800/50 shadow-2xl backdrop-blur-xl rounded-3xl p-6 flex flex-col gap-6 items-center">
    
    <!-- Brand Header -->
    <div class="flex flex-col items-center text-center gap-2 w-full pb-4 border-b border-zinc-200/50 dark:border-zinc-800/50">
        <img src="/images/ValleyInventoryService-Logo-Transparent.png" alt="Valley Inventory Service Logo" class="h-10 w-auto dark:brightness-110">
        <div class="flex flex-col">
            <flux:heading size="md" class="font-extrabold tracking-tight">VALLEY INVENTORY SERVICE</flux:heading>
            <flux:text size="xs" class="text-zinc-500">Employee Time Portal</flux:text>
        </div>
    </div>

    <!-- Dynamic Digital Clock -->
    <div x-data="{ 
             time: '', 
             date: '',
             updateTime() {
                 const now = new Date();
                 this.time = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
                 this.date = now.toLocaleDateString([], { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
             }
         }"
         x-init="updateTime(); setInterval(() => updateTime(), 1000);"
         class="flex flex-col items-center justify-center py-3 px-6 bg-slate-50/50 dark:bg-zinc-950/40 rounded-2xl border border-zinc-200/50 dark:border-zinc-800/50 backdrop-blur-sm shadow-sm w-full">
        <span class="text-3xl font-black tracking-widest text-slate-800 dark:text-zinc-100 font-mono" x-text="time"></span>
        <span class="text-[10px] font-semibold text-slate-500 dark:text-zinc-400 mt-1 uppercase tracking-widest" x-text="date"></span>
    </div>

    <!-- SCREEN 1: PIN Input Panel -->
    <div x-show="!validated" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         class="w-full flex flex-col gap-6 items-center">
        
        <!-- PIN Visualizer & Label -->
        <div class="flex flex-col items-center gap-2 w-full">
            <span class="text-xs font-bold uppercase tracking-wider text-zinc-400">Enter 4-Digit Pin</span>
            <div class="flex justify-center gap-4 my-1">
                <template x-for="i in 4">
                    <div class="w-4 h-4 rounded-full border-2 border-zinc-300 dark:border-zinc-600 transition-all duration-150"
                         :class="pin.length >= i ? 'bg-zinc-800 dark:bg-zinc-200 scale-110 shadow-md' : 'bg-transparent'"></div>
                </template>
            </div>
        </div>

        <!-- 10-Key Pad: pure black labels on white keys (ignore dark-mode grey text) -->
        <div class="grid grid-cols-3 gap-3 w-full max-w-[280px] mx-auto text-black">
            <template x-for="num in [1, 2, 3, 4, 5, 6, 7, 8, 9]">
                <button type="button"
                        @click="addDigit(num.toString())"
                        class="aspect-square flex items-center justify-center text-xl font-bold rounded-2xl bg-white hover:bg-zinc-100 active:scale-95 transition-all !text-black dark:!text-black border border-zinc-300 shadow-sm"
                        style="color: #000000;">
                    <span class="!text-black dark:!text-black font-bold" style="color: #000000;" x-text="num"></span>
                </button>
            </template>
            
            <!-- Clear Button -->
            <button type="button"
                    @click="clear()"
                    class="aspect-square flex items-center justify-center text-xs font-black rounded-2xl bg-white hover:bg-zinc-100 active:scale-95 transition-all !text-black dark:!text-black border border-zinc-300 shadow-sm"
                    style="color: #000000;">
                CLEAR
            </button>
            
            <!-- 0 Button -->
            <button type="button"
                    @click="addDigit('0')"
                    class="aspect-square flex items-center justify-center text-xl font-bold rounded-2xl bg-white hover:bg-zinc-100 active:scale-95 transition-all !text-black dark:!text-black border border-zinc-300 shadow-sm"
                    style="color: #000000;">
                0
            </button>
            
            <!-- Delete (Backspace) Button -->
            <button type="button"
                    @click="backspace()"
                    class="aspect-square flex items-center justify-center text-xs font-bold rounded-2xl bg-white hover:bg-zinc-100 active:scale-95 transition-all !text-black dark:!text-black border border-zinc-300 shadow-sm"
                    style="color: #000000;">
                DELETE
            </button>
        </div>
    </div>

    <!-- SCREEN 2: Action Select Panel -->
    <div x-show="validated" 
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 transform scale-95"
         x-transition:enter-end="opacity-100 transform scale-100"
         x-data="{ timer: 15, interval: null }"
         x-init="
             $watch('validated', (val) => {
                 if (val) {
                     timer = 15;
                     clearInterval(interval);
                     interval = setInterval(() => {
                         timer--;
                         if (timer <= 0) {
                             clearInterval(interval);
                             $wire.cancel();
                         }
                     }, 1000);
                 } else {
                     clearInterval(interval);
                 }
             })
         "
         class="w-full flex flex-col gap-6 items-center">
        
        <!-- Employee Greeting & Current Status -->
        <div class="flex flex-col items-center gap-1 text-center w-full">
            <span class="text-xs font-bold text-zinc-400 uppercase tracking-widest">Identified Employee</span>
            <flux:heading size="xl" class="font-black text-slate-800 dark:text-zinc-100" x-text="validated"></flux:heading>
            
            <!-- Current Status Badge -->
            @if ($validatedPin)
                @php
                    $empState = $employeeStates[$validatedPin] ?? 'clocked_out';
                @endphp
                <div class="mt-2">
                    @if ($empState === 'clocked_out')
                        <flux:badge color="zinc" size="sm" class="uppercase font-bold tracking-wider">Status: Clocked Out</flux:badge>
                    @elseif ($empState === 'clocked_in')
                        <flux:badge color="emerald" size="sm" class="uppercase font-bold tracking-wider">Status: Clocked In</flux:badge>
                    @elseif ($empState === 'on_break')
                        <flux:badge color="amber" size="sm" class="uppercase font-bold tracking-wider">Status: On Break</flux:badge>
                    @endif
                </div>
            @endif
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col gap-3 w-full max-w-[280px]">
            @if ($validatedPin)
                @php
                    $empState = $employeeStates[$validatedPin] ?? 'clocked_out';
                @endphp

                @if ($empState === 'clocked_out')
                    <!-- CLOCK IN -->
                    <button type="button"
                            wire:click="performAction('in')"
                            class="w-full py-4 text-sm font-black rounded-2xl bg-emerald-600 hover:bg-emerald-500 active:scale-95 transition-all text-white shadow-md flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 01-3-3h7a3 3 0 013 3v1" />
                        </svg>
                        CLOCK IN
                    </button>
                @endif

                @if ($empState === 'clocked_in')
                    <!-- START BREAK -->
                    <button type="button"
                            wire:click="performAction('start_break')"
                            class="w-full py-4 text-sm font-black rounded-2xl bg-amber-500 hover:bg-amber-400 active:scale-95 transition-all text-white shadow-md flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        START BREAK
                    </button>

                    <!-- CLOCK OUT -->
                    <button type="button"
                            wire:click="performAction('out')"
                            class="w-full py-4 text-sm font-black rounded-2xl bg-rose-600 hover:bg-rose-500 active:scale-95 transition-all text-white shadow-md flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        CLOCK OUT
                    </button>
                @endif

                @if ($empState === 'on_break')
                    <!-- END BREAK -->
                    <button type="button"
                            wire:click="performAction('end_break')"
                            class="w-full py-4 text-sm font-black rounded-2xl bg-emerald-600 hover:bg-emerald-500 active:scale-95 transition-all text-white shadow-md flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        END BREAK
                    </button>

                    <!-- CLOCK OUT (Ends Break and Clocks Out) -->
                    <button type="button"
                            wire:click="performAction('out')"
                            class="w-full py-4 text-sm font-black rounded-2xl bg-rose-600 hover:bg-rose-500 active:scale-95 transition-all text-white shadow-md flex items-center justify-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        CLOCK OUT
                    </button>
                @endif
            @endif

            <!-- CANCEL -->
            <button type="button"
                    wire:click="cancel"
                    class="w-full py-3 text-xs font-bold rounded-xl border border-zinc-200 dark:border-zinc-800 hover:bg-zinc-100 dark:hover:bg-zinc-800 text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200 active:scale-95 transition-all mt-2">
                CANCEL
            </button>
        </div>

        <!-- Auto-cancel timer indicator -->
        <span class="text-[9px] font-semibold tracking-wider text-zinc-400 uppercase">
            Auto-canceling in <span x-text="timer" class="font-bold text-zinc-600 dark:text-zinc-200"></span>s...
        </span>
    </div>

    <!-- Geolocation Map (Below the Keypad or Action Panel) -->
    <div class="flex flex-col gap-2 w-full mt-2">
        <div class="flex justify-between items-center px-1">
            <span class="text-[10px] font-bold uppercase tracking-wider text-zinc-400">GPS Geolocation</span>
            <div class="flex gap-3 flex-wrap justify-end">
                <flux:modal.trigger name="background-tasks-modal">
                    <button type="button" wire:click="bgRefreshList" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 flex items-center gap-1 text-[10px] font-bold transition-all">
                        <flux:icon name="clock" class="w-3.5 h-3.5" />
                        TASKS
                    </button>
                </flux:modal.trigger>
                <flux:modal.trigger name="vibration-modal">
                    <button type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 flex items-center gap-1 text-[10px] font-bold transition-all">
                        <flux:icon name="bolt" class="w-3.5 h-3.5" />
                        HAPTIC
                    </button>
                </flux:modal.trigger>
                <flux:modal.trigger name="secure-storage-modal">
                    <button type="button" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 flex items-center gap-1 text-[10px] font-bold transition-all">
                        <flux:icon name="key" class="w-3.5 h-3.5" />
                        KEYCHAIN
                    </button>
                </flux:modal.trigger>
                <button type="button" wire:click="refreshLocation" class="text-zinc-400 hover:text-zinc-600 dark:hover:text-zinc-200 flex items-center gap-1 text-[10px] font-bold transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                    REFRESH
                </button>
            </div>
        </div>
        
        <div x-data="{
                 lat: @entangle('latitude'),
                 lon: @entangle('longitude'),
                 map: null,
                 marker: null,
                 initMap() {
                     this.$watch('lat', () => this.updateMap());
                     this.$watch('lon', () => this.updateMap());
                     this.updateMap();
                 },
                 updateMap() {
                     if (!this.lat || !this.lon) return;
                     if (!this.map) {
                         this.map = L.map(this.$refs.mapDiv, { zoomControl: false }).setView([this.lat, this.lon], 14);
                         L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                             attribution: '&copy; OpenStreetMap'
                         }).addTo(this.map);
                         this.marker = L.marker([this.lat, this.lon]).addTo(this.map);
                         L.control.zoom({ position: 'bottomright' }).addTo(this.map);
                     } else {
                         this.map.setView([this.lat, this.lon], 14);
                         this.marker.setLatLng([this.lat, this.lon]);
                     }
                 }
             }"
             x-init="initMap()"
             wire:ignore
             class="w-full h-40 rounded-2xl overflow-hidden border border-zinc-200/50 dark:border-zinc-800/50 shadow-inner relative z-10">
            <div x-ref="mapDiv" class="w-full h-full"></div>
        </div>
    </div>

    <!-- Bottom Feedback Alert (Clock-in Result) -->
    @if ($status)
        <div x-data="{ show: true }" 
             x-show="show" 
             x-init="setTimeout(() => show = false, 5000)"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 transform scale-95"
             x-transition:enter-end="opacity-100 transform scale-100"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 transform scale-100"
             x-transition:leave-end="opacity-0 transform scale-95"
             class="w-full p-3 bg-emerald-50 border border-emerald-200 dark:bg-emerald-950/20 dark:border-emerald-900/40 text-emerald-800 dark:text-emerald-400 rounded-2xl flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-xs font-semibold">{{ $status }}</span>
            </div>
            <button type="button" @click="show = false" class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-500 dark:hover:text-emerald-300">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    @endif

    <flux:modal name="background-tasks-modal" class="md:w-[28rem] space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">Background Tasks</flux:heading>
            <flux:text size="sm" class="text-zinc-500">
                CRUD test for on-device scheduled artisan jobs (WorkManager / BGTaskScheduler).
            </flux:text>
        </div>

        @if ($bgTaskStatusMessage)
            <div class="p-2.5 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/40 text-blue-800 dark:text-blue-400 rounded-xl text-xs font-semibold">
                {{ $bgTaskStatusMessage }}
            </div>
        @endif

        <div class="space-y-3">
            <flux:field>
                <flux:label>Name</flux:label>
                <flux:input wire:model="bgTaskName" placeholder="e.g. hourly-inspire" />
            </flux:field>

            <flux:field>
                <flux:label>Artisan command</flux:label>
                <flux:input wire:model="bgTaskCommand" placeholder="e.g. inspire" />
            </flux:field>

            <flux:field>
                <flux:label>Interval (minutes, min 15)</flux:label>
                <flux:input type="number" wire:model.number="bgTaskInterval" min="15" step="1" />
            </flux:field>

            <flux:field>
                <flux:label>Task id (for update / delete / run)</flux:label>
                <flux:input wire:model="bgTaskId" placeholder="Select from list or paste id" />
            </flux:field>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <flux:button wire:click="bgCreate" variant="primary" class="w-full">Create</flux:button>
            <flux:button wire:click="bgUpdate" variant="outline" class="w-full">Update</flux:button>
            <flux:button wire:click="bgDelete" variant="danger" class="w-full">Delete</flux:button>
            <flux:button wire:click="bgRefreshList" variant="ghost" class="w-full">List</flux:button>
            <flux:button wire:click="bgSync" variant="filled" class="w-full">Sync OS</flux:button>
            <flux:button wire:click="bgRunNow" variant="primary" class="w-full" icon="play">Run Now</flux:button>
        </div>

        @if (count($bgTasks) > 0)
            <div class="space-y-2">
                <flux:heading size="sm">Registered tasks</flux:heading>
                <div class="max-h-40 overflow-y-auto space-y-1.5 rounded-xl border border-zinc-200/60 dark:border-zinc-800 p-2">
                    @foreach ($bgTasks as $task)
                        <button
                            type="button"
                            wire:key="bg-task-{{ $task['id'] ?? $loop->index }}"
                            wire:click="bgSelect('{{ $task['id'] ?? '' }}')"
                            class="w-full text-left px-2.5 py-2 rounded-lg bg-zinc-50 dark:bg-zinc-950/40 hover:bg-zinc-100 dark:hover:bg-zinc-900 border border-zinc-200/50 dark:border-zinc-800 text-xs"
                        >
                            <div class="font-bold text-zinc-800 dark:text-zinc-100">{{ $task['name'] ?? '—' }}</div>
                            <div class="font-mono text-[10px] text-zinc-500 truncate">{{ $task['command'] ?? '' }} · {{ $task['intervalMinutes'] ?? '?' }}m</div>
                            <div class="font-mono text-[9px] text-zinc-400 truncate">{{ $task['id'] ?? '' }}</div>
                        </button>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($bgTaskOutput !== '')
            <div class="space-y-1">
                <flux:heading size="sm">Task output</flux:heading>
                <pre class="p-3 max-h-48 overflow-auto rounded-xl bg-zinc-950 text-emerald-300 text-[11px] font-mono whitespace-pre-wrap break-all border border-zinc-800">{{ $bgTaskOutput }}</pre>
            </div>
        @endif
    </flux:modal>

    <flux:modal name="vibration-modal" class="md:w-96 space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">Haptic Feedback</flux:heading>
            <flux:text size="sm" class="text-zinc-500">
                Trigger device vibration patterns (iOS Core Haptics / Android VibrationEffect).
                @if ($supportsHaptics)
                    Device reports haptics support.
                @else
                    Haptics not detected (web or unsupported device).
                @endif
            </flux:text>
        </div>

        @if ($vibrationStatusMessage)
            <div class="p-2.5 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/40 text-blue-800 dark:text-blue-400 rounded-xl text-xs font-semibold">
                {{ $vibrationStatusMessage }}
            </div>
        @endif

        <div class="grid grid-cols-2 gap-2">
            <flux:button wire:click="vibrateTap" variant="outline" class="w-full">Tap</flux:button>
            <flux:button wire:click="vibrateSuccess" variant="primary" class="w-full">Success</flux:button>
            <flux:button wire:click="vibrateError" variant="danger" class="w-full">Error</flux:button>
            <flux:button wire:click="vibrateCancel" variant="ghost" class="w-full">Cancel</flux:button>
        </div>
    </flux:modal>

    <flux:modal name="secure-storage-modal" class="md:w-96 space-y-4">
        <div class="space-y-1">
            <flux:heading size="lg">Secure Storage (Keychain)</flux:heading>
            <flux:text size="sm" class="text-zinc-500">Save, retrieve, or delete sensitive data securely.</flux:text>
        </div>

        @if ($secureStorageStatusMessage)
            <div class="p-2.5 bg-blue-50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/40 text-blue-800 dark:text-blue-400 rounded-xl text-xs font-semibold">
                {{ $secureStorageStatusMessage }}
            </div>
        @endif

        <div class="space-y-3">
            <flux:field>
                <flux:label>Key</flux:label>
                <flux:input wire:model="secureStorageKey" placeholder="e.g. auth_token" />
            </flux:field>

            <flux:field>
                <flux:label>Value (Only required for Save)</flux:label>
                <flux:input wire:model="secureStorageValue" placeholder="e.g. my-super-secret-token" />
            </flux:field>

            @if ($secureStorageResult)
                <flux:field>
                    <flux:label>Retrieved Value</flux:label>
                    <div class="p-3 bg-zinc-50 dark:bg-zinc-950/40 border border-zinc-200/50 dark:border-zinc-800/50 rounded-xl font-mono text-sm break-all">
                        {{ $secureStorageResult }}
                    </div>
                </flux:field>
            @endif
        </div>

        <div class="flex gap-2 justify-end mt-4">
            <flux:button wire:click="secureGet" variant="outline">Retrieve</flux:button>
            <flux:button wire:click="secureDelete" variant="danger">Delete</flux:button>
            <flux:button wire:click="secureSave" variant="primary">Save</flux:button>
        </div>
    </flux:modal>

</div>