package com.nativephp.mobile.bridge.functions

import android.content.Context
import android.util.Log
import androidx.fragment.app.FragmentActivity
import androidx.security.crypto.EncryptedSharedPreferences
import androidx.security.crypto.MasterKeys
import com.nativephp.mobile.bridge.BridgeFunction

class SecureStorageFunctions {

    companion object {
        private fun getEncryptedPrefs(context: Context): EncryptedSharedPreferences {
            val masterKeyAlias = MasterKeys.getOrCreate(MasterKeys.AES256_GCM_SPEC)
            return EncryptedSharedPreferences.create(
                "secure_storage_prefs",
                masterKeyAlias,
                context,
                EncryptedSharedPreferences.PrefKeyEncryptionScheme.AES256_SIV,
                EncryptedSharedPreferences.PrefValueEncryptionScheme.AES256_GCM
            )
        }
    }

    class Set(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d("SecureStorage", "Set: Executing SecureStorage.Set")
            val key = parameters["key"] as? String
            val value = parameters["value"] as? String

            if (key == null || value == null) {
                return mapOf("success" to false, "error" to "Invalid parameters. 'key' and 'value' are required.")
            }

            return try {
                val prefs = getEncryptedPrefs(context)
                prefs.edit().putString(key, value).apply()
                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e("SecureStorage", "Set: Failed to write to secure storage: ${e.message}")
                mapOf("success" to false, "error" to (e.message ?: "Unknown error occurred during write"))
            }
        }
    }

    class Get(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d("SecureStorage", "Get: Executing SecureStorage.Get")
            val key = parameters["key"] as? String

            if (key == null) {
                return mapOf("success" to false, "error" to "Invalid parameters. 'key' is required.")
            }

            return try {
                val prefs = getEncryptedPrefs(context)
                val value = prefs.getString(key, null)
                mapOf("success" to true, "value" to (value as Any? ?: ""))
            } catch (e: Exception) {
                Log.e("SecureStorage", "Get: Failed to read from secure storage: ${e.message}")
                mapOf("success" to false, "error" to (e.message ?: "Unknown error occurred during read"))
            }
        }
    }

    class Delete(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d("SecureStorage", "Delete: Executing SecureStorage.Delete")
            val key = parameters["key"] as? String

            if (key == null) {
                return mapOf("success" to false, "error" to "Invalid parameters. 'key' is required.")
            }

            return try {
                val prefs = getEncryptedPrefs(context)
                if (prefs.contains(key)) {
                    prefs.edit().remove(key).apply()
                }
                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e("SecureStorage", "Delete: Failed to delete from secure storage: ${e.message}")
                mapOf("success" to false, "error" to (e.message ?: "Unknown error occurred during delete"))
            }
        }
    }
}
