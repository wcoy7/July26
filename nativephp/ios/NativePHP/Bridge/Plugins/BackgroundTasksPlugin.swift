import Foundation

/// Persistent registry of background task definitions (CRUD).
/// Inspired by nativephp/mobile-background-tasks — stores task metadata on-device
/// so PHP can schedule/manage jobs that map to OS background execution later.
enum BackgroundTasksStore {
    private static let storageKey = "nativephp.background_tasks"
    private static let queue = DispatchQueue(label: "nativephp.background_tasks.store")

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
}

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
            return ["success": true]
        }
    }
}
