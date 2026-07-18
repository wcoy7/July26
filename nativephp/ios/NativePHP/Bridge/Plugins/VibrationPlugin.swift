import Foundation
import CoreHaptics

/// Shared Core Haptics engine for single vibrations and patterns.
enum VibrationEngine {
    private static var engine: CHHapticEngine?
    private static var player: CHHapticAdvancedPatternPlayer?
    private static let lock = NSLock()

    static func ensureEngine() throws -> CHHapticEngine {
        lock.lock()
        defer { lock.unlock() }

        if let engine {
            return engine
        }

        guard CHHapticEngine.capabilitiesForHardware().supportsHaptics else {
            throw NSError(domain: "Vibration", code: 1, userInfo: [
                NSLocalizedDescriptionKey: "Device does not support haptics",
            ])
        }

        let newEngine = try CHHapticEngine()
        newEngine.isAutoShutdownEnabled = true
        try newEngine.start()
        engine = newEngine

        return newEngine
    }

    static func supportsHaptics() -> Bool {
        CHHapticEngine.capabilitiesForHardware().supportsHaptics
    }

    static func stop() {
        lock.lock()
        defer { lock.unlock() }

        try? player?.stop(atTime: CHHapticTimeImmediate)
        player = nil
        engine?.stop(completionHandler: nil)
        engine = nil
    }

    static func play(events: [CHHapticEvent]) throws {
        let engine = try ensureEngine()
        try engine.start()

        let pattern = try CHHapticPattern(events: events, parameters: [])
        let newPlayer = try engine.makeAdvancedPlayer(with: pattern)
        player = newPlayer
        try newPlayer.start(atTime: CHHapticTimeImmediate)
    }

    static func intValue(_ value: Any?) -> Int? {
        if let i = value as? Int { return i }
        if let n = value as? NSNumber { return n.intValue }
        if let d = value as? Double { return Int(d) }
        if let s = value as? String { return Int(s) }
        return nil
    }

    static func floatValue(_ value: Any?) -> Double? {
        if let d = value as? Double { return d }
        if let f = value as? Float { return Double(f) }
        if let i = value as? Int { return Double(i) }
        if let n = value as? NSNumber { return n.doubleValue }
        if let s = value as? String { return Double(s) }
        return nil
    }
}

enum VibrationFunctions {

    class Vibrate: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard VibrationEngine.supportsHaptics() else {
                return ["success": false, "error": "Device does not support haptics"]
            }

            let durationMs = VibrationEngine.intValue(parameters["duration"]) ?? 100
            let intensity = VibrationEngine.floatValue(parameters["intensity"]) ?? 0.5
            let sharpness = VibrationEngine.floatValue(parameters["sharpness"]) ?? 0.5

            let clampedDuration = max(1, min(5000, durationMs))
            let clampedIntensity = max(0.0, min(1.0, intensity))
            let clampedSharpness = max(0.0, min(1.0, sharpness))
            let durationSeconds = Double(clampedDuration) / 1000.0

            let intensityParam = CHHapticEventParameter(parameterID: .hapticIntensity, value: Float(clampedIntensity))
            let sharpnessParam = CHHapticEventParameter(parameterID: .hapticSharpness, value: Float(clampedSharpness))

            let event = CHHapticEvent(
                eventType: .hapticContinuous,
                parameters: [intensityParam, sharpnessParam],
                relativeTime: 0,
                duration: max(0.01, durationSeconds)
            )

            do {
                try VibrationEngine.play(events: [event])
                return ["success": true]
            } catch {
                return ["success": false, "error": error.localizedDescription]
            }
        }
    }

    class HasHaptics: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let supported = VibrationEngine.supportsHaptics()
            return ["success": true, "supported": supported]
        }
    }

    class Cancel: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            VibrationEngine.stop()
            return ["success": true]
        }
    }

    class PlayPattern: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard VibrationEngine.supportsHaptics() else {
                return ["success": false, "error": "Device does not support haptics"]
            }

            guard let steps = parameters["steps"] as? [[String: Any]], !steps.isEmpty else {
                return ["success": false, "error": "Invalid parameters. 'steps' array is required."]
            }

            var events: [CHHapticEvent] = []
            var cursor: TimeInterval = 0

            for step in steps {
                let type = (step["type"] as? String) ?? "vibrate"
                let durationMs = VibrationEngine.intValue(step["duration"]) ?? 0
                let durationSeconds = Double(max(0, min(5000, durationMs))) / 1000.0

                if type == "pause" {
                    cursor += durationSeconds
                    continue
                }

                let intensity = max(0.0, min(1.0, VibrationEngine.floatValue(step["intensity"]) ?? 0.5))
                let sharpness = max(0.0, min(1.0, VibrationEngine.floatValue(step["sharpness"]) ?? 0.5))

                let intensityParam = CHHapticEventParameter(parameterID: .hapticIntensity, value: Float(intensity))
                let sharpnessParam = CHHapticEventParameter(parameterID: .hapticSharpness, value: Float(sharpness))

                let eventDuration = max(0.01, durationSeconds)
                let event = CHHapticEvent(
                    eventType: .hapticContinuous,
                    parameters: [intensityParam, sharpnessParam],
                    relativeTime: cursor,
                    duration: eventDuration
                )
                events.append(event)
                cursor += eventDuration
            }

            guard !events.isEmpty else {
                return ["success": false, "error": "Pattern contains no vibration steps"]
            }

            do {
                try VibrationEngine.play(events: events)
                return ["success": true]
            } catch {
                return ["success": false, "error": error.localizedDescription]
            }
        }
    }
}
