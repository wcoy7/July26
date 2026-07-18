import Foundation
import Security

enum SecureStorageFunctions {
    
    class Set: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let key = parameters["key"] as? String,
                  let value = parameters["value"] as? String else {
                return ["success": false, "error": "Invalid parameters. 'key' and 'value' are required."]
            }
            
            guard let data = value.data(using: .utf8) else {
                return ["success": false, "error": "Failed to convert value to data"]
            }
            
            // Delete existing item first to avoid duplicate key conflict
            let query: [String: Any] = [
                kSecClass as String: kSecClassGenericPassword,
                kSecAttrAccount as String: key
            ]
            SecItemDelete(query as CFDictionary)
            
            // Add new item
            let attributes: [String: Any] = [
                kSecClass as String: kSecClassGenericPassword,
                kSecAttrAccount as String: key,
                kSecValueData as String: data,
                kSecAttrAccessible as String: kSecAttrAccessibleWhenUnlockedThisDeviceOnly
            ]
            
            let status = SecItemAdd(attributes as CFDictionary, nil)
            
            if status == errSecSuccess {
                return ["success": true]
            } else {
                return ["success": false, "error": "Keychain write failed with status \(status)"]
            }
        }
    }
    
    class Get: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let key = parameters["key"] as? String else {
                return ["success": false, "error": "Invalid parameters. 'key' is required."]
            }
            
            let query: [String: Any] = [
                kSecClass as String: kSecClassGenericPassword,
                kSecAttrAccount as String: key,
                kSecReturnData as String: kCFBooleanTrue!,
                kSecMatchLimit as String: kSecMatchLimitOne
            ]
            
            var dataTypeRef: AnyObject?
            let status = SecItemCopyMatching(query as CFDictionary, &dataTypeRef)
            
            if status == errSecSuccess {
                if let data = dataTypeRef as? Data,
                   let value = String(data: data, encoding: .utf8) {
                    return ["success": true, "value": value]
                }
                return ["success": false, "error": "Failed to decode value from Keychain"]
            } else if status == errSecItemNotFound {
                // NSNull serializes to JSON null; plain nil is invalid in [String: Any]
                return ["success": true, "value": NSNull()]
            } else {
                return ["success": false, "error": "Keychain read failed with status \(status)"]
            }
        }
    }
    
    class Delete: NSObject, BridgeFunction {
        func execute(parameters: [String: Any]) throws -> [String: Any] {
            guard let key = parameters["key"] as? String else {
                return ["success": false, "error": "Invalid parameters. 'key' is required."]
            }
            
            let query: [String: Any] = [
                kSecClass as String: kSecClassGenericPassword,
                kSecAttrAccount as String: key
            ]
            
            let status = SecItemDelete(query as CFDictionary)
            
            if status == errSecSuccess || status == errSecItemNotFound {
                return ["success": true]
            } else {
                return ["success": false, "error": "Keychain delete failed with status \(status)"]
            }
        }
    }
}
