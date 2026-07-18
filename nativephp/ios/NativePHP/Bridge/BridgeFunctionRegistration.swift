import Foundation

/// Register all bridge functions with the registry
/// Call this once during app initialization
func registerBridgeFunctions() {
    let registry = BridgeFunctionRegistry.shared

    registry.register("Edge.Set", function: EdgeFunctions.Set())
    registry.register("Geolocation.GetLocation", function: GeolocationFunctions.GetLocation())
    registry.register("SecureStorage.Set", function: SecureStorageFunctions.Set())
    registry.register("SecureStorage.Get", function: SecureStorageFunctions.Get())
    registry.register("SecureStorage.Delete", function: SecureStorageFunctions.Delete())
    registry.register("Vibration.Vibrate", function: VibrationFunctions.Vibrate())
    registry.register("Vibration.HasHaptics", function: VibrationFunctions.HasHaptics())
    registry.register("Vibration.Cancel", function: VibrationFunctions.Cancel())
    registry.register("Vibration.PlayPattern", function: VibrationFunctions.PlayPattern())
    registry.register("BackgroundTasks.Create", function: BackgroundTasksFunctions.Create())
    registry.register("BackgroundTasks.Get", function: BackgroundTasksFunctions.Get())
    registry.register("BackgroundTasks.List", function: BackgroundTasksFunctions.List())
    registry.register("BackgroundTasks.Update", function: BackgroundTasksFunctions.Update())
    registry.register("BackgroundTasks.Delete", function: BackgroundTasksFunctions.Delete())
    registry.register("BackgroundTasks.Sync", function: BackgroundTasksFunctions.Sync())
    registry.register("BackgroundTasks.RunNow", function: BackgroundTasksFunctions.RunNow())
    registry.register("LocalNotifications.RequestPermission", function: LocalNotificationsFunctions.RequestPermission())
    registry.register("LocalNotifications.HasPermission", function: LocalNotificationsFunctions.HasPermission())
    registry.register("LocalNotifications.Show", function: LocalNotificationsFunctions.Show())
    registry.register("LocalNotifications.Schedule", function: LocalNotificationsFunctions.Schedule())
    registry.register("LocalNotifications.Cancel", function: LocalNotificationsFunctions.Cancel())
    registry.register("LocalNotifications.CancelAll", function: LocalNotificationsFunctions.CancelAll())

    // BGTaskScheduler handlers must be registered before launch ends
    NativePHPPluginRegistry.shared.registerOnAppLaunch("BackgroundTasks") {
        BackgroundTasksScheduler.registerHandlersIfNeeded()
    }
    // After PHP is ready, schedule enabled tasks with the OS
    NativePHPPluginRegistry.shared.registerOnAppReady("BackgroundTasks") {
        BackgroundTasksScheduler.rescheduleAll()
    }

    // Register plugin bridge functions
    registerPluginBridgeFunctions()
}
