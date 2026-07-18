package com.nativephp.mobile.bridge

import android.content.Context
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.functions.EdgeFunctions
import com.nativephp.mobile.bridge.functions.SecureStorageFunctions
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

    // Register plugin bridge functions
    registerPluginBridgeFunctions(activity, context)
}