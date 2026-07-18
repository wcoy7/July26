package com.nativephp.mobile.bridge

import android.content.Context
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.functions.BackgroundTasksFunctions
import com.nativephp.mobile.bridge.functions.EdgeFunctions
import com.nativephp.mobile.bridge.functions.GeolocationFunctions
import com.nativephp.mobile.bridge.functions.LocalNotificationsFunctions
import com.nativephp.mobile.bridge.functions.ScannerFunctions
import com.nativephp.mobile.bridge.functions.SecureStorageFunctions
import com.nativephp.mobile.bridge.functions.VibrationFunctions
import com.nativephp.mobile.bridge.plugins.registerPluginBridgeFunctions

/**
 * Register all bridge functions with the registry
 * Call this once during app initialization
 */
fun registerBridgeFunctions(activity: FragmentActivity, context: Context) {
    val registry = BridgeFunctionRegistry.shared

    registry.register("Edge.Set", EdgeFunctions.Set())
    registry.register("Geolocation.GetLocation", GeolocationFunctions.GetLocation(activity, context))
    registry.register("SecureStorage.Set", SecureStorageFunctions.Set(context))
    registry.register("SecureStorage.Get", SecureStorageFunctions.Get(context))
    registry.register("SecureStorage.Delete", SecureStorageFunctions.Delete(context))
    registry.register("Vibration.Vibrate", VibrationFunctions.Vibrate(context))
    registry.register("Vibration.HasHaptics", VibrationFunctions.HasHaptics(context))
    registry.register("Vibration.Cancel", VibrationFunctions.Cancel(context))
    registry.register("Vibration.PlayPattern", VibrationFunctions.PlayPattern(context))
    registry.register("BackgroundTasks.Create", BackgroundTasksFunctions.Create(context))
    registry.register("BackgroundTasks.Get", BackgroundTasksFunctions.Get(context))
    registry.register("BackgroundTasks.List", BackgroundTasksFunctions.List(context))
    registry.register("BackgroundTasks.Update", BackgroundTasksFunctions.Update(context))
    registry.register("BackgroundTasks.Delete", BackgroundTasksFunctions.Delete(context))
    registry.register("BackgroundTasks.Sync", BackgroundTasksFunctions.Sync(context))
    registry.register("BackgroundTasks.RunNow", BackgroundTasksFunctions.RunNow(context))
    registry.register("LocalNotifications.RequestPermission", LocalNotificationsFunctions.RequestPermission(activity, context))
    registry.register("LocalNotifications.HasPermission", LocalNotificationsFunctions.HasPermission(context))
    registry.register("LocalNotifications.Show", LocalNotificationsFunctions.Show(context))
    registry.register("LocalNotifications.Schedule", LocalNotificationsFunctions.Schedule(context))
    registry.register("LocalNotifications.Cancel", LocalNotificationsFunctions.Cancel(context))
    registry.register("LocalNotifications.CancelAll", LocalNotificationsFunctions.CancelAll(context))
    registry.register("Scanner.Scan", ScannerFunctions.Scan(activity))

    // Re-sync WorkManager schedules after PHP/app is ready
    try {
        BackgroundTasksScheduler.rescheduleAll(context)
    } catch (e: Exception) {
        android.util.Log.w("BridgeRegistration", "BackgroundTasks schedule bootstrap failed: ${e.message}")
    }

    try {
        LocalNotificationsFunctions.ensureChannel(context)
    } catch (e: Exception) {
        android.util.Log.w("BridgeRegistration", "LocalNotifications channel setup failed: ${e.message}")
    }

    // Register plugin bridge functions
    registerPluginBridgeFunctions(activity, context)
}