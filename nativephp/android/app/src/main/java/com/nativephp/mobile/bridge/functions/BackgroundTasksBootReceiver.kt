package com.nativephp.mobile.bridge.functions

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.util.Log

/**
 * Re-registers WorkManager periodic tasks after device boot or app update.
 */
class BackgroundTasksBootReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent?) {
        Log.i(TAG, "Boot/update received: ${intent?.action}")
        try {
            BackgroundTasksScheduler.rescheduleAll(context.applicationContext)
        } catch (e: Exception) {
            Log.e(TAG, "Failed to reschedule background tasks", e)
        }
    }

    companion object {
        private const val TAG = "BackgroundTasksBoot"
    }
}
