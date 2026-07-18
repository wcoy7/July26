import Foundation
import UserNotifications
import UIKit

/// Ensures banners appear while the app is in the foreground.
final class LocalNotificationCenterDelegate: NSObject, UNUserNotificationCenterDelegate {
    static let shared = LocalNotificationCenterDelegate()

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        willPresent notification: UNNotification,
        withCompletionHandler completionHandler: @escaping (UNNotificationPresentationOptions) -> Void
    ) {
        if #available(iOS 14.0, *) {
            completionHandler([.banner, .list, .sound, .badge])
        } else {
            completionHandler([.alert, .sound, .badge])
        }
    }

    func userNotificationCenter(
        _ center: UNUserNotificationCenter,
        didReceive response: UNNotificationResponse,
        withCompletionHandler completionHandler: @escaping () -> Void
    ) {
        completionHandler()
    }

    static func install() {
        UNUserNotificationCenter.current().delegate = shared
    }
}

/// Local notification helpers for iOS (notification center — user-dismissible).
enum LocalNotificationsHelper {
    static func string(_ value: Any?, default defaultValue: String = "") -> String {
        if let s = value as? String { return s }
        if let n = value as? NSNumber { return n.stringValue }
        return defaultValue
    }

    static func intValue(_ value: Any?, default defaultValue: Int) -> Int {
        if let i = value as? Int { return i }
        if let n = value as? NSNumber { return n.intValue }
        if let s = value as? String, let i = Int(s) { return i }
        return defaultValue
    }

    static func content(title: String, body: String) -> UNMutableNotificationContent {
        let content = UNMutableNotificationContent()
        content.title = title.isEmpty ? "Notification" : title
        content.body = body.isEmpty ? " " : body
        content.sound = .default
        // Do NOT set .timeSensitive — without the entitlement iOS can drop delivery.
        if #available(iOS 15.0, *) {
            content.interruptionLevel = .active
        }
        return content
    }

    /// Best-effort deliver: works in foreground (delegate banners) and background.
    static func deliver(title: String, body: String, id: String) {
        LocalNotificationCenterDelegate.install()

        let notificationContent = Self.content(title: title, body: body)
        // nil trigger = deliver immediately (more reliable than a sub-second interval).
        let request = UNNotificationRequest(identifier: id, content: notificationContent, trigger: nil)
        let center = UNUserNotificationCenter.current()

        let addRequest = {
            DispatchQueue.main.async {
                LocalNotificationCenterDelegate.install()
                center.add(request) { error in
                    if let error {
                        print("LocalNotifications deliver failed id=\(id): \(error.localizedDescription)")
                    } else {
                        print("LocalNotifications deliver ok id=\(id) title=\(title)")
                    }
                }
            }
        }

        center.getNotificationSettings { settings in
            print("LocalNotifications settings status=\(settings.authorizationStatus.rawValue) alert=\(settings.alertSetting.rawValue)")
            switch settings.authorizationStatus {
            case .authorized, .provisional, .ephemeral:
                addRequest()
            case .notDetermined:
                center.requestAuthorization(options: [.alert, .badge, .sound]) { granted, error in
                    if let error {
                        print("LocalNotifications permission error: \(error)")
                    }
                    print("LocalNotifications permission granted=\(granted)")
                    if granted {
                        addRequest()
                    }
                }
            default:
                print("LocalNotifications: not authorized — open Settings or NOTIFY → Allow")
                // Still attempt add (no-op when denied) for consistent logging
                addRequest()
            }
        }
    }
}

enum LocalNotificationsFunctions {

    class RequestPermission: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let semaphore = DispatchSemaphore(value: 0)
            var granted = false
            var errorMessage: String?

            UNUserNotificationCenter.current().requestAuthorization(options: [.alert, .badge, .sound]) { ok, error in
                granted = ok
                errorMessage = error?.localizedDescription
                semaphore.signal()
            }

            _ = semaphore.wait(timeout: .now() + 30)

            if let errorMessage {
                return ["success": true, "granted": granted, "error": errorMessage]
            }
            return ["success": true, "granted": granted]
        }
    }

    class HasPermission: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let semaphore = DispatchSemaphore(value: 0)
            var granted = false

            UNUserNotificationCenter.current().getNotificationSettings { settings in
                switch settings.authorizationStatus {
                case .authorized, .provisional, .ephemeral:
                    granted = true
                default:
                    granted = false
                }
                semaphore.signal()
            }

            _ = semaphore.wait(timeout: .now() + 10)
            return ["success": true, "granted": granted]
        }
    }

    class Show: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let title = LocalNotificationsHelper.string(parameters["title"])
            let body = LocalNotificationsHelper.string(parameters["body"])
            let id = LocalNotificationsHelper.string(parameters["id"], default: UUID().uuidString)

            guard !title.isEmpty || !body.isEmpty else {
                return ["success": false, "error": "title or body is required"]
            }

            // Fire-and-forget — never main.async + semaphore (deadlocks Livewire).
            LocalNotificationsHelper.deliver(title: title, body: body, id: id)

            return ["success": true, "id": id, "queued": true]
        }
    }

    class Schedule: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let title = LocalNotificationsHelper.string(parameters["title"])
            let body = LocalNotificationsHelper.string(parameters["body"])
            let id = LocalNotificationsHelper.string(parameters["id"], default: UUID().uuidString)
            let delay = max(1, LocalNotificationsHelper.intValue(parameters["delaySeconds"], default: 5))

            guard !title.isEmpty || !body.isEmpty else {
                return ["success": false, "error": "title or body is required"]
            }

            let content = LocalNotificationsHelper.content(title: title, body: body)
            let trigger = UNTimeIntervalNotificationTrigger(timeInterval: TimeInterval(delay), repeats: false)
            let request = UNNotificationRequest(identifier: id, content: content, trigger: trigger)

            let semaphore = DispatchSemaphore(value: 0)
            var errorMessage: String?

            UNUserNotificationCenter.current().add(request) { error in
                errorMessage = error?.localizedDescription
                semaphore.signal()
            }

            _ = semaphore.wait(timeout: .now() + 10)

            if let errorMessage {
                return ["success": false, "error": errorMessage, "id": id]
            }
            return ["success": true, "id": id, "delaySeconds": delay]
        }
    }

    class Cancel: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let id = LocalNotificationsHelper.string(parameters["id"])
            guard !id.isEmpty else {
                return ["success": false, "error": "id is required"]
            }

            let center = UNUserNotificationCenter.current()
            center.removePendingNotificationRequests(withIdentifiers: [id])
            center.removeDeliveredNotifications(withIdentifiers: [id])
            return ["success": true, "id": id]
        }
    }

    class CancelAll: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            let center = UNUserNotificationCenter.current()
            center.removeAllPendingNotificationRequests()
            center.removeAllDeliveredNotifications()
            return ["success": true]
        }
    }
}
