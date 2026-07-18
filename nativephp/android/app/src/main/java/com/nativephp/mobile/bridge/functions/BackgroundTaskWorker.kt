package com.nativephp.mobile.bridge.functions

import android.content.Context
import android.util.Log
import androidx.work.Worker
import androidx.work.WorkerParameters
import com.nativephp.mobile.bridge.LaravelEnvironment
import com.nativephp.mobile.bridge.PHPBridge

/**
 * WorkManager worker that boots an ephemeral PHP runtime and runs one artisan command.
 */
class BackgroundTaskWorker(
    context: Context,
    params: WorkerParameters,
) : Worker(context, params) {

    override fun doWork(): Result {
        val command = inputData.getString(KEY_COMMAND)?.trim().orEmpty()
        val taskId = inputData.getString(KEY_TASK_ID)

        if (command.isEmpty()) {
            Log.e(TAG, "Missing command for taskId=$taskId")
            return Result.failure()
        }

        Log.i(TAG, "Running background task id=$taskId command='$command'")

        return try {
            val env = LaravelEnvironment(applicationContext)
            env.initializeForBackground()

            val bridge = PHPBridge(applicationContext)
            val output = bridge.runEphemeralArtisan(command)
            Log.i(TAG, "Task finished id=$taskId output=${output.take(300)}")

            if (!taskId.isNullOrBlank()) {
                BackgroundTasksScheduler.markLastRun(applicationContext, taskId)
            }

            BackgroundTasksScheduler.notifyTaskCompleted(
                applicationContext,
                taskId ?: "unknown",
                command,
                output,
                success = true
            )

            Result.success()
        } catch (e: Exception) {
            Log.e(TAG, "Background task failed id=$taskId", e)
            BackgroundTasksScheduler.notifyTaskCompleted(
                applicationContext,
                taskId ?: "unknown",
                command,
                e.message ?: "error",
                success = false
            )
            Result.retry()
        }
    }

    companion object {
        private const val TAG = "BackgroundTaskWorker"
        const val KEY_COMMAND = "command"
        const val KEY_TASK_ID = "taskId"
    }
}
