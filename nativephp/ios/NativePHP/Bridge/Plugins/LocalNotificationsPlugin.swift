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
        content.title = title
        content.body = body
        content.sound = .default
        if #available(iOS 15.0, *) {
            content.interruptionLevel = .timeSensitive
        }
        return content
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

            let semaphore = DispatchSemaphore(value: 0)
            var errorMessage: String?
            var ok = false

            DispatchQueue.main.async {
                LocalNotificationCenterDelegate.install()
                let center = UNUserNotificationCenter.current()

                let deliver: () -> Void = {
                    let content = LocalNotificationsHelper.content(title: title, body: body)
                    let request = UNNotificationRequest(identifier: id, content: content, trigger: nil)
                    center.add(request) { error in
                        if let error {
                            errorMessage = error.localizedDescription
                        } else {
                            ok = true
                        }
                        semaphore.signal()
                    }
                }

                center.getNotificationSettings { settings in
                    switch settings.authorizationStatus {
                    case .authorized, .provisional, .ephemeral:
                        deliver()
                    case .notDetermined:
                        center.requestAuthorization(options: [.alert, .badge, .sound]) { granted, error in
                            if let error {
                                errorMessage = error.localizedDescription
                                semaphore.signal()
                            } else if granted {
                                deliver()
                            } else {
                                errorMessage = "Notification permission denied"
                                semaphore.signal()
                            }
                        }
                    default:
                        errorMessage = "Notification permission not granted. Enable in Settings → Notifications."
                        semaphore.signal()
                    }
                }
            }

            _ = semaphore.wait(timeout: .now() + 30)

            if let errorMessage {
                return ["success": false, "error": errorMessage, "id": id]
            }
            if ok {
                return ["success": true, "id": id]
            }
            return ["success": false, "error": "Failed to deliver notification", "id": id]
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
