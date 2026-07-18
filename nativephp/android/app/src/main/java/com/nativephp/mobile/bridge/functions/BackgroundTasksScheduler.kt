package com.nativephp.mobile.bridge.functions

import android.content.Context
import android.util.Log
import androidx.work.Constraints
import androidx.work.Data
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import org.json.JSONObject
import java.util.concurrent.TimeUnit

/**
 * Schedules enabled background tasks with WorkManager and runs them immediately for testing.
 */
object BackgroundTasksScheduler {
    private const val TAG = "BackgroundTasksScheduler"
    private const val WORK_PREFIX = "nativephp_bg_task_"
    private const val LAST_RUN_PREFS = "nativephp_background_tasks_last_run"

    fun rescheduleAll(context: Context) {
        val tasks = BackgroundTasksFunctions.loadTasks(context)
        val wm = WorkManager.getInstance(context)

        // Cancel work for deleted / disabled tasks
        val enabledIds = tasks
            .filter { it.optBoolean("enabled", true) }
            .map { it.optString("id") }
            .filter { it.isNotBlank() }
            .toSet()

        // Unique work names we own are only for known task ids; cancel orphans by re-enqueuing policy.
        for (task in tasks) {
            val id = task.optString("id")
            if (id.isBlank()) continue
            val workName = workName(id)

            if (!task.optBoolean("enabled", true)) {
                wm.cancelUniqueWork(workName)
                continue
            }

            val command = task.optString("command").ifBlank { task.optString("name") }
            if (command.isBlank()) {
                wm.cancelUniqueWork(workName)
                continue
            }

            val intervalMinutes = maxOf(15L, task.optLong("intervalMinutes", 15L))
            val constraints = buildConstraints(task.optJSONObject("constraints"))

            val input = Data.Builder()
                .putString(BackgroundTaskWorker.KEY_COMMAND, command)
                .putString(BackgroundTaskWorker.KEY_TASK_ID, id)
                .build()

            val request = PeriodicWorkRequestBuilder<BackgroundTaskWorker>(
                intervalMinutes,
                TimeUnit.MINUTES
            )
                .setConstraints(constraints)
                .setInputData(input)
                .addTag(WORK_PREFIX)
                .build()

            wm.enqueueUniquePeriodicWork(
                workName,
                ExistingPeriodicWorkPolicy.UPDATE,
                request
            )
            Log.i(TAG, "Scheduled task id=$id every ${intervalMinutes}m command='$command'")
        }

        // Cancel work for tasks that no longer exist
        // WorkManager doesn't list unique names easily; cancel by missing id when we track them in prefs
        val known = BackgroundTasksFunctions.loadTasks(context).map { it.optString("id") }.toSet()
        // Persist last known ids
        val prefs = context.getSharedPreferences("nativephp_background_tasks_meta", Context.MODE_PRIVATE)
        val previous = prefs.getStringSet("scheduled_ids", emptySet()) ?: emptySet()
        for (oldId in previous) {
            if (oldId !in known || oldId !in enabledIds) {
                wm.cancelUniqueWork(workName(oldId))
                Log.i(TAG, "Cancelled stale work for id=$oldId")
            }
        }
        prefs.edit().putStringSet("scheduled_ids", enabledIds).apply()
    }

    fun cancel(context: Context, taskId: String) {
        WorkManager.getInstance(context).cancelUniqueWork(workName(taskId))
    }

    fun runNow(context: Context, onlyId: String? = null): List<Map<String, Any>> {
        val results = mutableListOf<Map<String, Any>>()
        val tasks = BackgroundTasksFunctions.loadTasks(context)
            .filter { it.optBoolean("enabled", true) }
            .filter { onlyId == null || it.optString("id") == onlyId }

        for (task in tasks) {
            val id = task.optString("id")
            val command = task.optString("command").ifBlank { task.optString("name") }
            if (command.isBlank()) continue

            try {
                val env = com.nativephp.mobile.bridge.LaravelEnvironment(context)
                env.initializeForBackground()
                val bridge = com.nativephp.mobile.bridge.PHPBridge(context)
                val output = bridge.runEphemeralArtisan(command)
                markLastRun(context, id)
                notifyTaskCompleted(context, id, command, output, success = true)
                results.add(
                    mapOf(
                        "id" to id,
                        "command" to command,
                        "output" to output,
                        "success" to true,
                    )
                )
            } catch (e: Exception) {
                Log.e(TAG, "runNow failed for $id", e)
                notifyTaskCompleted(context, id, command, e.message ?: "error", success = false)
                results.add(
                    mapOf(
                        "id" to id,
                        "command" to command,
                        "output" to (e.message ?: "error"),
                        "success" to false,
                    )
                )
            }
        }

        return results
    }

    fun markLastRun(context: Context, taskId: String) {
        context.getSharedPreferences(LAST_RUN_PREFS, Context.MODE_PRIVATE)
            .edit()
            .putLong(taskId, System.currentTimeMillis())
            .apply()
    }

    /**
     * System tray notification so you can verify WorkManager background runs
     * without watching the app UI.
     */
    fun notifyTaskCompleted(
        context: Context,
        taskId: String,
        command: String,
        output: String,
        success: Boolean,
    ) {
        try {
            LocalNotificationsFunctions.ensureChannel(context)

            if (!LocalNotificationsFunctions.hasPermission(context)) {
                Log.w(TAG, "Task notification: permission not granted — attempting post anyway may fail")
            }

            val title = if (success) "Background task finished" else "Background task failed"
            val snippet = output.trim().replace(Regex("\\s+"), " ")
            val body = if (snippet.isEmpty()) {
                "Command: $command"
            } else {
                "$command — ${snippet.take(160)}"
            }

            LocalNotificationsFunctions.showNotification(
                context,
                title,
                body,
                "bg_task_${taskId}_${System.currentTimeMillis()}"
            )
            Log.i(TAG, "Posted completion notification for $command")
        } catch (e: Exception) {
            Log.e(TAG, "Failed to post task notification: ${e.message}", e)
        }
    }

    private fun workName(taskId: String): String = WORK_PREFIX + taskId

    private fun buildConstraints(raw: JSONObject?): Constraints {
        val builder = Constraints.Builder()
        if (raw == null) {
            return builder.build()
        }

        when {
            raw.optBoolean("onWifi", false) -> builder.setRequiredNetworkType(NetworkType.UNMETERED)
            raw.optBoolean("onAnyNetwork", false) -> builder.setRequiredNetworkType(NetworkType.CONNECTED)
            else -> builder.setRequiredNetworkType(NetworkType.NOT_REQUIRED)
        }

        if (raw.optBoolean("whileCharging", false)) {
            builder.setRequiresCharging(true)
        }
        if (raw.optBoolean("whenBatteryNotLow", false)) {
            builder.setRequiresBatteryNotLow(true)
        }
        if (raw.optBoolean("whenStorageNotLow", false)) {
            builder.setRequiresStorageNotLow(true)
        }
        if (raw.optBoolean("whenIdle", false)) {
            builder.setRequiresDeviceIdle(true)
        }

        return builder.build()
    }
}
