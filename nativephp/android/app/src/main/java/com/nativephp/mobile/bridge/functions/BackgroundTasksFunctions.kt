package com.nativephp.mobile.bridge.functions

import android.content.Context
import android.util.Log
import com.nativephp.mobile.bridge.BridgeFunction
import org.json.JSONArray
import org.json.JSONObject
import java.time.Instant
import java.util.UUID

class BackgroundTasksFunctions {

    companion object {
        private const val TAG = "BackgroundTasks"
        private const val PREFS = "nativephp_background_tasks"
        private const val KEY = "tasks"
        private const val MIN_INTERVAL = 15

        private fun prefs(context: Context) =
            context.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

        fun loadTasks(context: Context): MutableList<JSONObject> {
            val raw = prefs(context).getString(KEY, "[]") ?: "[]"
            val array = JSONArray(raw)
            val list = mutableListOf<JSONObject>()
            for (i in 0 until array.length()) {
                list.add(array.getJSONObject(i))
            }
            return list
        }

        fun saveTasks(context: Context, tasks: List<JSONObject>) {
            val array = JSONArray()
            tasks.forEach { array.put(it) }
            prefs(context).edit().putString(KEY, array.toString()).apply()
        }

        fun defaultConstraints(): JSONObject = JSONObject()
            .put("onAnyNetwork", false)
            .put("onWifi", false)
            .put("whileCharging", false)
            .put("whenBatteryNotLow", false)
            .put("whenStorageNotLow", false)
            .put("whenIdle", false)

        fun mergeConstraints(raw: Any?): JSONObject {
            val constraints = defaultConstraints()
            val source = when (raw) {
                is JSONObject -> raw
                is Map<*, *> -> JSONObject(raw as Map<*, *>)
                else -> return constraints
            }
            for (key in constraints.keys()) {
                if (source.has(key)) {
                    constraints.put(key, source.optBoolean(key, false))
                }
            }
            return constraints
        }

        fun toMap(obj: JSONObject): Map<String, Any> {
            val map = mutableMapOf<String, Any>()
            val keys = obj.keys()
            while (keys.hasNext()) {
                val key = keys.next()
                val value = obj.get(key)
                map[key] = when (value) {
                    is JSONObject -> toMap(value)
                    is JSONArray -> {
                        val list = mutableListOf<Any>()
                        for (i in 0 until value.length()) {
                            val item = value.get(i)
                            list.add(if (item is JSONObject) toMap(item) else item)
                        }
                        list
                    }
                    JSONObject.NULL -> ""
                    else -> value
                }
            }
            return map
        }

        fun intParam(parameters: Map<String, Any>, key: String, default: Int): Int {
            return when (val value = parameters[key]) {
                is Number -> value.toInt()
                is String -> value.toIntOrNull() ?: default
                else -> default
            }
        }

        fun boolParam(parameters: Map<String, Any>, key: String, default: Boolean): Boolean {
            return when (val value = parameters[key]) {
                is Boolean -> value
                is Number -> value.toInt() != 0
                is String -> value.equals("true", true) || value == "1"
                else -> default
            }
        }

        fun nowIso(): String = Instant.now().toString()
    }

    class Create(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d(TAG, "Create")
            val name = (parameters["name"] as? String)?.trim().orEmpty()
            if (name.isEmpty()) {
                return mapOf("success" to false, "error" to "Invalid parameters. 'name' is required.")
            }

            val command = (parameters["command"] as? String)?.trim().orEmpty().ifEmpty { name }
            val interval = maxOf(MIN_INTERVAL, intParam(parameters, "intervalMinutes", MIN_INTERVAL))
            val now = nowIso()

            val task = JSONObject()
                .put("id", UUID.randomUUID().toString().lowercase())
                .put("name", name)
                .put("command", command)
                .put("intervalMinutes", interval)
                .put("enabled", boolParam(parameters, "enabled", true))
                .put("longRunning", boolParam(parameters, "longRunning", false))
                .put("constraints", mergeConstraints(parameters["constraints"]))
                .put("createdAt", now)
                .put("updatedAt", now)

            val tasks = loadTasks(context)
            tasks.add(task)
            saveTasks(context, tasks)
            BackgroundTasksScheduler.rescheduleAll(context)

            return mapOf("success" to true, "task" to toMap(task))
        }
    }

    class Get(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
            if (id.isNullOrBlank()) {
                return mapOf("success" to false, "error" to "Invalid parameters. 'id' is required.")
            }

            val task = loadTasks(context).firstOrNull { it.optString("id") == id }
                ?: return mapOf("success" to false, "error" to "Task not found")

            return mapOf("success" to true, "task" to toMap(task))
        }
    }

    class List(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val tasks = loadTasks(context).map { toMap(it) }
            return mapOf("success" to true, "tasks" to tasks)
        }
    }

    class Update(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
            if (id.isNullOrBlank()) {
                return mapOf("success" to false, "error" to "Invalid parameters. 'id' is required.")
            }

            val tasks = loadTasks(context)
            val index = tasks.indexOfFirst { it.optString("id") == id }
            if (index < 0) {
                return mapOf("success" to false, "error" to "Task not found")
            }

            val task = tasks[index]

            (parameters["name"] as? String)?.trim()?.takeIf { it.isNotEmpty() }?.let {
                task.put("name", it)
            }
            (parameters["command"] as? String)?.trim()?.takeIf { it.isNotEmpty() }?.let {
                task.put("command", it)
            }
            if (parameters.containsKey("intervalMinutes")) {
                task.put("intervalMinutes", maxOf(MIN_INTERVAL, intParam(parameters, "intervalMinutes", MIN_INTERVAL)))
            }
            if (parameters.containsKey("enabled")) {
                task.put("enabled", boolParam(parameters, "enabled", true))
            }
            if (parameters.containsKey("longRunning")) {
                task.put("longRunning", boolParam(parameters, "longRunning", false))
            }
            if (parameters.containsKey("constraints")) {
                task.put("constraints", mergeConstraints(parameters["constraints"]))
            }

            task.put("updatedAt", nowIso())
            tasks[index] = task
            saveTasks(context, tasks)
            BackgroundTasksScheduler.rescheduleAll(context)

            return mapOf("success" to true, "task" to toMap(task))
        }
    }

    class Delete(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val id = parameters["id"] as? String
            if (id.isNullOrBlank()) {
                return mapOf("success" to false, "error" to "Invalid parameters. 'id' is required.")
            }

            val tasks = loadTasks(context)
            val removed = tasks.removeAll { it.optString("id") == id }
            if (!removed) {
                return mapOf("success" to false, "error" to "Task not found")
            }

            saveTasks(context, tasks)
            BackgroundTasksScheduler.cancel(context, id)
            BackgroundTasksScheduler.rescheduleAll(context)
            return mapOf("success" to true)
        }
    }

    class Sync(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            BackgroundTasksScheduler.rescheduleAll(context)
            return mapOf("success" to true, "count" to loadTasks(context).size)
        }
    }

    class RunNow(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val onlyId = parameters["id"] as? String
            val results = BackgroundTasksScheduler.runNow(context, onlyId)
            return mapOf("success" to true, "results" to results)
        }
    }
}
