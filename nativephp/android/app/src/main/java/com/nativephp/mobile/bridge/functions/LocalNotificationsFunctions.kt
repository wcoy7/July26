package com.nativephp.mobile.bridge.functions

import android.Manifest
import android.app.AlarmManager
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Build
import android.util.Log
import androidx.core.app.ActivityCompat
import androidx.core.app.NotificationCompat
import androidx.core.app.NotificationManagerCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.ui.MainActivity
import java.util.UUID

class LocalNotificationsFunctions {

    companion object {
        private const val TAG = "LocalNotifications"
        const val CHANNEL_ID = "nativephp_local_notifications"
        const val CHANNEL_NAME = "App notifications"
        const val EXTRA_TITLE = "title"
        const val EXTRA_BODY = "body"
        const val EXTRA_ID = "notification_id"
        const val ACTION_SHOW = "com.nativephp.mobile.SHOW_LOCAL_NOTIFICATION"

        fun ensureChannel(context: Context) {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val manager = context.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
                // HIGH so heads-up banners are more likely while testing
                val channel = NotificationChannel(
                    CHANNEL_ID,
                    CHANNEL_NAME,
                    NotificationManager.IMPORTANCE_HIGH
                ).apply {
                    description = "Local notifications from the app"
                    enableVibration(true)
                    setShowBadge(true)
                }
                manager.createNotificationChannel(channel)
            }
        }

        fun notificationIdFromString(id: String): Int {
            return id.hashCode() and 0x7FFFFFFF
        }

        fun hasPermission(context: Context): Boolean {
            return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                ContextCompat.checkSelfPermission(
                    context,
                    Manifest.permission.POST_NOTIFICATIONS
                ) == PackageManager.PERMISSION_GRANTED
            } else {
                NotificationManagerCompat.from(context).areNotificationsEnabled()
            }
        }

        fun showNotification(context: Context, title: String, body: String, id: String) {
            ensureChannel(context)

            val launchIntent = Intent(context, MainActivity::class.java).apply {
                flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            }
            val contentIntent = PendingIntent.getActivity(
                context,
                notificationIdFromString(id),
                launchIntent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )

            val builder = NotificationCompat.Builder(context, CHANNEL_ID)
                .setSmallIcon(android.R.drawable.ic_dialog_info)
                .setContentTitle(title.ifBlank { "Notification" })
                .setContentText(body.ifBlank { " " })
                .setStyle(NotificationCompat.BigTextStyle().bigText(body.ifBlank { title }))
                .setPriority(NotificationCompat.PRIORITY_HIGH)
                .setCategory(NotificationCompat.CATEGORY_STATUS)
                .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
                .setDefaults(NotificationCompat.DEFAULT_ALL)
                .setAutoCancel(true)
                .setContentIntent(contentIntent)

            val nm = NotificationManagerCompat.from(context)
            if (!nm.areNotificationsEnabled()) {
                Log.w(TAG, "Notifications disabled in system settings — post may be dropped")
            }
            try {
                nm.notify(notificationIdFromString(id), builder.build())
                Log.i(TAG, "Notification posted id=$id title=$title")
            } catch (e: SecurityException) {
                Log.e(TAG, "Notification permission denied", e)
                throw e
            }
        }

        fun stringParam(parameters: Map<String, Any>, key: String, default: String = ""): String {
            return when (val v = parameters[key]) {
                is String -> v
                is Number -> v.toString()
                else -> default
            }
        }

        fun intParam(parameters: Map<String, Any>, key: String, default: Int): Int {
            return when (val v = parameters[key]) {
                is Number -> v.toInt()
                is String -> v.toIntOrNull() ?: default
                else -> default
            }
        }
    }

    class RequestPermission(private val activity: FragmentActivity, private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            ensureChannel(context)

            if (Build.VERSION.SDK_INT < Build.VERSION_CODES.TIRAMISU) {
                val granted = NotificationManagerCompat.from(context).areNotificationsEnabled()
                return mapOf("success" to true, "granted" to granted)
            }

            val granted = hasPermission(context)
            if (!granted) {
                activity.runOnUiThread {
                    ActivityCompat.requestPermissions(
                        activity,
                        arrayOf(Manifest.permission.POST_NOTIFICATIONS),
                        9101
                    )
                }
                // Permission result is async; report current state
                return mapOf(
                    "success" to true,
                    "granted" to false,
                    "error" to "Permission requested; check HasPermission after user responds."
                )
            }

            return mapOf("success" to true, "granted" to true)
        }
    }

    class HasPermission(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return mapOf("success" to true, "granted" to hasPermission(context))
        }
    }

    class Show(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val title = stringParam(parameters, "title")
            val body = stringParam(parameters, "body")
            val id = stringParam(parameters, "id").ifBlank { UUID.randomUUID().toString() }

            if (title.isBlank() && body.isBlank()) {
                return mapOf("success" to false, "error" to "title or body is required")
            }

            if (!hasPermission(context)) {
                return mapOf("success" to false, "error" to "Notification permission not granted", "id" to id)
            }

            return try {
                showNotification(context, title, body, id)
                mapOf("success" to true, "id" to id)
            } catch (e: Exception) {
                Log.e(TAG, "Show failed", e)
                mapOf("success" to false, "error" to (e.message ?: "Failed to show notification"), "id" to id)
            }
        }
    }

    class Schedule(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val title = stringParam(parameters, "title")
            val body = stringParam(parameters, "body")
            val id = stringParam(parameters, "id").ifBlank { UUID.randomUUID().toString() }
            val delaySeconds = maxOf(1, intParam(parameters, "delaySeconds", 5))

            if (title.isBlank() && body.isBlank()) {
                return mapOf("success" to false, "error" to "title or body is required")
            }

            if (!hasPermission(context)) {
                return mapOf("success" to false, "error" to "Notification permission not granted", "id" to id)
            }

            return try {
                val intent = Intent(context, LocalNotificationReceiver::class.java).apply {
                    action = ACTION_SHOW
                    putExtra(EXTRA_TITLE, title)
                    putExtra(EXTRA_BODY, body)
                    putExtra(EXTRA_ID, id)
                }

                val pending = PendingIntent.getBroadcast(
                    context,
                    notificationIdFromString(id),
                    intent,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )

                val triggerAt = System.currentTimeMillis() + delaySeconds * 1000L
                val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager

                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M) {
                    alarmManager.setAndAllowWhileIdle(AlarmManager.RTC_WAKEUP, triggerAt, pending)
                } else {
                    alarmManager.set(AlarmManager.RTC_WAKEUP, triggerAt, pending)
                }

                mapOf("success" to true, "id" to id, "delaySeconds" to delaySeconds)
            } catch (e: Exception) {
                Log.e(TAG, "Schedule failed", e)
                mapOf("success" to false, "error" to (e.message ?: "Failed to schedule"), "id" to id)
            }
        }
    }

    class Cancel(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = stringParam(parameters, "id")
            if (id.isBlank()) {
                return mapOf("success" to false, "error" to "id is required")
            }

            val nid = notificationIdFromString(id)
            NotificationManagerCompat.from(context).cancel(nid)

            val intent = Intent(context, LocalNotificationReceiver::class.java).apply {
                action = ACTION_SHOW
            }
            val pending = PendingIntent.getBroadcast(
                context,
                nid,
                intent,
                PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
            )
            val alarmManager = context.getSystemService(Context.ALARM_SERVICE) as AlarmManager
            alarmManager.cancel(pending)

            return mapOf("success" to true, "id" to id)
        }
    }

    class CancelAll(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            NotificationManagerCompat.from(context).cancelAll()
            return mapOf("success" to true)
        }
    }
}

/**
 * Fires a scheduled local notification.
 */
class LocalNotificationReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent?) {
        if (intent?.action != LocalNotificationsFunctions.ACTION_SHOW) return

        val title = intent.getStringExtra(LocalNotificationsFunctions.EXTRA_TITLE) ?: ""
        val body = intent.getStringExtra(LocalNotificationsFunctions.EXTRA_BODY) ?: ""
        val id = intent.getStringExtra(LocalNotificationsFunctions.EXTRA_ID)
            ?: UUID.randomUUID().toString()

        try {
            LocalNotificationsFunctions.showNotification(context, title, body, id)
        } catch (e: Exception) {
            Log.e("LocalNotificationReceiver", "Failed to show scheduled notification", e)
        }
    }
}
