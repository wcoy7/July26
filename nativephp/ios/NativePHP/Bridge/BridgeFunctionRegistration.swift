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

    // Register plugin bridge functions
    registerPluginBridgeFunctions()
}
