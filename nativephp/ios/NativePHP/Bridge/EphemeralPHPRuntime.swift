import Foundation

// Ephemeral PHP runtime C bindings (independent of the UI/persistent PHP thread).
@_silgen_name("ephemeral_php_boot")
private func _ephemeral_php_boot(_ bootstrapPath: UnsafePointer<CChar>?) -> Int32

@_silgen_name("ephemeral_php_artisan")
private func _ephemeral_php_artisan(_ command: UnsafePointer<CChar>?) -> UnsafePointer<CChar>?

@_silgen_name("ephemeral_php_shutdown")
private func _ephemeral_php_shutdown()

/**
 Boots a short-lived PHP thread, runs one artisan command, then shuts down.

 Use this for background-task execution so Run Now never re-enters the same
 PHP pthread that is serving Livewire — which previously hung forever and
 meant completion notifications never fired.
 */
enum EphemeralPHPRuntime {
    private static let queue = DispatchQueue(label: "nativephp.ephemeral-php", qos: .userInitiated)

    /// Run artisan off the persistent/UI PHP worker. Blocks the calling thread until done.
    static func artisan(command: String) -> String {
        // Serialize ephemeral boot/run/shutdown (C layer is single-instance).
        queue.sync {
            runArtisanLocked(command: command)
        }
    }

    private static func runArtisanLocked(command: String) -> String {
        let appPath = AppUpdateManager.shared.getAppPath()
        // Same bootstrap Android uses for ephemeral (persistent.php boots Laravel console).
        let bootstrapPath = appPath + "/vendor/nativephp/mobile/bootstrap/ios/persistent.php"

        guard FileManager.default.fileExists(atPath: bootstrapPath) else {
            print("EphemeralPHPRuntime: bootstrap missing at \(bootstrapPath)")
            return "error: ephemeral bootstrap missing"
        }

        // Ensure process-wide env matches persistent boot (ephemeral piggybacks on SAPI).
        setenv("NATIVEPHP_RUNNING", "true", 1)
        setenv("NATIVEPHP_PLATFORM", "ios", 1)
        setenv("APP_RUNNING_IN_CONSOLE", "true", 1)

        let bootCode: Int32 = bootstrapPath.withCString { cBootstrap in
            _ephemeral_php_boot(cBootstrap)
        }

        guard bootCode == 0 else {
            print("EphemeralPHPRuntime: boot failed code=\(bootCode); falling back to persistent artisan")
            if PersistentPHPRuntime.shared.isBooted {
                return PersistentPHPRuntime.shared.artisan(command: command)
            }
            return "error: ephemeral PHP boot failed (\(bootCode))"
        }

        defer {
            _ephemeral_php_shutdown()
        }

        return command.withCString { cCommand in
            guard let resultPtr = _ephemeral_php_artisan(cCommand) else {
                return ""
            }
            let result = String(cString: resultPtr)
            free(UnsafeMutableRawPointer(mutating: resultPtr))
            print("EphemeralPHPRuntime: artisan '\(command)' => \(result.prefix(200))")
            return result
        }
    }
}
