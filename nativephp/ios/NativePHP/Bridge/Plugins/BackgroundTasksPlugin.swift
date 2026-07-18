import Foundation
import BackgroundTasks

// MARK: - Storage

/// Persistent registry of background task definitions (CRUD + OS scheduling).
enum BackgroundTasksStore {
    static let storageKey = "nativephp.background_tasks"
    static let lastRunKey = "nativephp.background_tasks.last_run"
    private static let queue = DispatchQueue(label: "nativephp.background_tasks.store")

    /// Shared identifier for periodic refresh (must match Info.plist).
    static let refreshIdentifier = "com.nativephp.background-tasks.refresh"
    /// Shared identifier for long-running / constrained work.
    static let processingIdentifier = "com.nativephp.background-tasks.processing"

    static func all() -> [[String: Any]] {
        queue.sync {
            guard let data = UserDefaults.standard.data(forKey: storageKey),
                  let json = try? JSONSerialization.jsonObject(with: data) as? [[String: Any]] else {
                return []
            }
            return json
        }
    }

    static func save(_ tasks: [[String: Any]]) {
        queue.sync {
            if let data = try? JSONSerialization.data(withJSONObject: tasks) {
                UserDefaults.standard.set(data, forKey: storageKey)
            }
        }
    }

    static func find(id: String) -> [String: Any]? {
        all().first { ($0["id"] as? String) == id }
    }

    static func updateLastRun(id: String, at date: Date = Date()) {
        queue.sync {
            var map = UserDefaults.standard.dictionary(forKey: lastRunKey) as? [String: Double] ?? [:]
            map[id] = date.timeIntervalSince1970
            UserDefaults.standard.set(map, forKey: lastRunKey)
        }
    }

    static func lastRun(id: String) -> Date? {
        queue.sync {
            let map = UserDefaults.standard.dictionary(forKey: lastRunKey) as? [String: Double] ?? [:]
            guard let ts = map[id] else { return nil }
            return Date(timeIntervalSince1970: ts)
        }
    }

    static func defaultConstraints() -> [String: Any] {
        [
            "onAnyNetwork": false,
            "onWifi": false,
            "whileCharging": false,
            "whenBatteryNotLow": false,
            "whenStorageNotLow": false,
            "whenIdle": false,
        ]
    }

    static func mergeConstraints(_ raw: Any?) -> [String: Any] {
        var constraints = defaultConstraints()
        guard let dict = raw as? [String: Any] else {
            return constraints
        }
        for key in constraints.keys {
            if let value = dict[key] as? Bool {
                constraints[key] = value
            } else if let number = dict[key] as? NSNumber {
                constraints[key] = number.boolValue
            }
        }
        return constraints
    }

    static func intValue(_ value: Any?, default defaultValue: Int) -> Int {
        if let i = value as? Int { return i }
        if let n = value as? NSNumber { return n.intValue }
        if let s = value as? String, let i = Int(s) { return i }
        return defaultValue
    }

    static func boolValue(_ value: Any?, default defaultValue: Bool) -> Bool {
        if let b = value as? Bool { return b }
        if let n = value as? NSNumber { return n.boolValue }
        if let s = value as? String {
            return ["1", "true", "yes"].contains(s.lowercased())
        }
        return defaultValue
    }

    static func isoNow() -> String {
        ISO8601DateFormatter().string(from: Date())
    }

    static func hasAnyConstraint(_ task: [String: Any]) -> Bool {
        guard let c = task["constraints"] as? [String: Any] else { return false }
        return c.values.contains { ($0 as? Bool) == true }
    }
}

// MARK: - OS Scheduler + Artisan runner

enum BackgroundTasksScheduler {
    private static var didRegisterHandlers = false

    /// Register BGTask handlers (once) and schedule pending work from the store.
    static func bootstrap() {
        registerHandlersIfNeeded()
        rescheduleAll()
        print("BackgroundTasksScheduler: bootstrapped")
    }

    static func registerHandlersIfNeeded() {
        guard !didRegisterHandlers else { return }
        didRegisterHandlers = true

        BGTaskScheduler.shared.register(forTaskWithIdentifier: BackgroundTasksStore.refreshIdentifier, using: nil) { task in
            handle(task: task, processing: false)
        }
        BGTaskScheduler.shared.register(forTaskWithIdentifier: BackgroundTasksStore.processingIdentifier, using: nil) { task in
            handle(task: task, processing: true)
        }
        print("BackgroundTasksScheduler: handlers registered")
    }

    /// Re-submit BGAppRefresh / BGProcessing requests based on enabled tasks.
    static func rescheduleAll() {
        registerHandlersIfNeeded()

        BGTaskScheduler.shared.cancel(taskRequestWithIdentifier: BackgroundTasksStore.refreshIdentifier)
        BGTaskScheduler.shared.cancel(taskRequestWithIdentifier: BackgroundTasksStore.processingIdentifier)

        let tasks = BackgroundTasksStore.all().filter {
            BackgroundTasksStore.boolValue($0["enabled"], default: true)
        }

        guard !tasks.isEmpty else {
            print("BackgroundTasksScheduler: no enabled tasks")
            return
        }

        let refreshTasks = tasks.filter { task in
            !BackgroundTasksStore.boolValue(task["longRunning"], default: false)
                && !BackgroundTasksStore.hasAnyConstraint(task)
        }
        let processingTasks = tasks.filter { task in
            BackgroundTasksStore.boolValue(task["longRunning"], default: false)
                || BackgroundTasksStore.hasAnyConstraint(task)
        }

        if !refreshTasks.isEmpty {
            let minInterval = refreshTasks
                .map { BackgroundTasksStore.intValue($0["intervalMinutes"], default: 15) }
                .min() ?? 15
            let request = BGAppRefreshTaskRequest(identifier: BackgroundTasksStore.refreshIdentifier)
            request.earliestBeginDate = Date(timeIntervalSinceNow: TimeInterval(minInterval * 60))
            do {
                try BGTaskScheduler.shared.submit(request)
                print("BackgroundTasksScheduler: scheduled refresh in \(minInterval)m")
            } catch {
                print("BackgroundTasksScheduler: failed to schedule refresh: \(error)")
            }
        }

        if !processingTasks.isEmpty {
            let minInterval = processingTasks
                .map { BackgroundTasksStore.intValue($0["intervalMinutes"], default: 15) }
                .min() ?? 15
            let request = BGProcessingTaskRequest(identifier: BackgroundTasksStore.processingIdentifier)
            request.earliestBeginDate = Date(timeIntervalSinceNow: TimeInterval(minInterval * 60))

            let needsNetwork = processingTasks.contains { task in
                let c = task["constraints"] as? [String: Any] ?? [:]
                return (c["onAnyNetwork"] as? Bool == true) || (c["onWifi"] as? Bool == true)
            }
            let needsPower = processingTasks.contains { task in
                let c = task["constraints"] as? [String: Any] ?? [:]
                return c["whileCharging"] as? Bool == true
            }
            request.requiresNetworkConnectivity = needsNetwork
            request.requiresExternalPower = needsPower

            do {
                try BGTaskScheduler.shared.submit(request)
                print("BackgroundTasksScheduler: scheduled processing in \(minInterval)m")
            } catch {
                print("BackgroundTasksScheduler: failed to schedule processing: \(error)")
            }
        }
    }

    private static func handle(task: BGTask, processing: Bool) {
        // Always reschedule before work so the OS has a next request.
        rescheduleAll()

        let queue = DispatchQueue(label: "nativephp.background_tasks.execute", qos: .utility)
        var finished = false
        let lock = NSLock()

        task.expirationHandler = {
            lock.lock()
            finished = true
            lock.unlock()
            task.setTaskCompleted(success: false)
        }

        queue.async {
            let results = executeDueTasks(processingOnly: processing)
            lock.lock()
            let alreadyFinished = finished
            finished = true
            lock.unlock()
            if !alreadyFinished {
                task.setTaskCompleted(success: true)
            }
            print("BackgroundTasksScheduler: completed \(results.count) task(s) processing=\(processing)")
        }
    }

    /// Run due enabled tasks. If forceAll, ignore last-run / interval.
    @discardableResult
    static func executeDueTasks(processingOnly: Bool? = nil, forceAll: Bool = false, onlyId: String? = nil) -> [[String: Any]] {
        ensurePhpReady()

        var results: [[String: Any]] = []
        let now = Date()

        for task in BackgroundTasksStore.all() {
            guard let id = task["id"] as? String else { continue }
            if let onlyId, onlyId != id { continue }
            guard BackgroundTasksStore.boolValue(task["enabled"], default: true) else { continue }

            let isLong = BackgroundTasksStore.boolValue(task["longRunning"], default: false)
                || BackgroundTasksStore.hasAnyConstraint(task)
            if let processingOnly {
                if processingOnly != isLong { continue }
            }

            if !forceAll {
                let intervalMin = BackgroundTasksStore.intValue(task["intervalMinutes"], default: 15)
                if let last = BackgroundTasksStore.lastRun(id: id),
                   now.timeIntervalSince(last) < Double(intervalMin * 60) {
                    continue
                }
            }

            let command = (task["command"] as? String)
                ?? (task["name"] as? String)
                ?? ""
            guard !command.isEmpty else { continue }

            let output = runArtisan(command: command)
            BackgroundTasksStore.updateLastRun(id: id, at: now)
            results.append([
                "id": id,
                "command": command,
                "output": output,
                "success": true,
            ])
        }

        return results
    }

    static func runArtisan(command: String) -> String {
        ensurePhpReady()
        if PersistentPHPRuntime.shared.isBooted {
            let out = PersistentPHPRuntime.shared.artisan(command: command)
            print("BackgroundTasksScheduler: artisan '\(command)' => \(out.prefix(200))")
            return out
        }
        print("BackgroundTasksScheduler: PHP runtime not booted, cannot run '\(command)'")
        return "error: PHP runtime not booted"
    }

    private static func ensurePhpReady() {
        if PersistentPHPRuntime.shared.isBooted {
            return
        }
        // Cold start from BGTaskScheduler — boot PHP if possible
        _ = AppUpdateManager.shared.ensureAppExists()
        _ = PersistentPHPRuntime.shared.boot()
    }
}

// MARK: - Bridge functions

enum BackgroundTasksFunctions {

    class Create: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let name = parameters["name"] as? String, !name.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty else {
                return ["success": false, "error": "Invalid parameters. 'name' is required."]
            }

            let trimmedName = name.trimmingCharacters(in: .whitespacesAndNewlines)
            let command = (parameters["command"] as? String)?.trimmingCharacters(in: .whitespacesAndNewlines)
            let resolvedCommand = (command?.isEmpty == false) ? command! : trimmedName
            let interval = max(15, BackgroundTasksStore.intValue(parameters["intervalMinutes"], default: 15))
            let enabled = BackgroundTasksStore.boolValue(parameters["enabled"], default: true)
            let longRunning = BackgroundTasksStore.boolValue(parameters["longRunning"], default: false)
            let now = BackgroundTasksStore.isoNow()

            let task: [String: Any] = [
                "id": UUID().uuidString.lowercased(),
                "name": trimmedName,
                "command": resolvedCommand,
                "intervalMinutes": interval,
                "enabled": enabled,
                "longRunning": longRunning,
                "constraints": BackgroundTasksStore.mergeConstraints(parameters["constraints"]),
                "createdAt": now,
                "updatedAt": now,
            ]

            var tasks = BackgroundTasksStore.all()
            tasks.append(task)
            BackgroundTasksStore.save(tasks)
            BackgroundTasksScheduler.rescheduleAll()

            return ["success": true, "task": task]
        }
    }

    class Get: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let id = parameters["id"] as? String, !id.isEmpty else {
                return ["success": false, "error": "Invalid parameters. 'id' is required."]
            }
            guard let task = BackgroundTasksStore.find(id: id) else {
                return ["success": false, "error": "Task not found"]
            }
            return ["success": true, "task": task]
        }
    }

    class List: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            return ["success": true, "tasks": BackgroundTasksStore.all()]
        }
    }

    class Update: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let id = parameters["id"] as? String, !id.isEmpty else {
                return ["success": false, "error": "Invalid parameters. 'id' is required."]
            }

            var tasks = BackgroundTasksStore.all()
            guard let index = tasks.firstIndex(where: { ($0["id"] as? String) == id }) else {
                return ["success": false, "error": "Task not found"]
            }

            var task = tasks[index]

            if let name = parameters["name"] as? String, !name.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                task["name"] = name.trimmingCharacters(in: .whitespacesAndNewlines)
            }
            if let command = parameters["command"] as? String, !command.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty {
                task["command"] = command.trimmingCharacters(in: .whitespacesAndNewlines)
            }
            if parameters["intervalMinutes"] != nil {
                task["intervalMinutes"] = max(15, BackgroundTasksStore.intValue(parameters["intervalMinutes"], default: 15))
            }
            if parameters["enabled"] != nil {
                task["enabled"] = BackgroundTasksStore.boolValue(parameters["enabled"], default: true)
            }
            if parameters["longRunning"] != nil {
                task["longRunning"] = BackgroundTasksStore.boolValue(parameters["longRunning"], default: false)
            }
            if parameters["constraints"] != nil {
                task["constraints"] = BackgroundTasksStore.mergeConstraints(parameters["constraints"])
            }

            task["updatedAt"] = BackgroundTasksStore.isoNow()
            tasks[index] = task
            BackgroundTasksStore.save(tasks)
            BackgroundTasksScheduler.rescheduleAll()

            return ["success": true, "task": task]
        }
    }

    class Delete: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let id = parameters["id"] as? String, !id.isEmpty else {
                return ["success": false, "error": "Invalid parameters. 'id' is required."]
            }

            var tasks = BackgroundTasksStore.all()
            let before = tasks.count
            tasks.removeAll { ($0["id"] as? String) == id }

            if tasks.count == before {
                return ["success": false, "error": "Task not found"]
            }

            BackgroundTasksStore.save(tasks)
            BackgroundTasksScheduler.rescheduleAll()
            return ["success": true]
        }
    }

    class Sync: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            BackgroundTasksScheduler.rescheduleAll()
            return ["success": true, "count": BackgroundTasksStore.all().count]
        }
    }

    class RunNow: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let onlyId = parameters["id"] as? String
            let results = BackgroundTasksScheduler.executeDueTasks(forceAll: true, onlyId: onlyId)
            return ["success": true, "results": results]
        }
    }
}
