package com.nativephp.mobile.bridge.functions

import android.content.Context
import android.os.Build
import android.os.VibrationEffect
import android.os.Vibrator
import android.os.VibratorManager
import android.util.Log
import com.nativephp.mobile.bridge.BridgeFunction

class VibrationFunctions {

    companion object {
        private const val TAG = "Vibration"

        @Suppress("DEPRECATION")
        fun vibrator(context: Context): Vibrator? {
            return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                val manager = context.getSystemService(Context.VIBRATOR_MANAGER_SERVICE) as? VibratorManager
                manager?.defaultVibrator
            } else {
                context.getSystemService(Context.VIBRATOR_SERVICE) as? Vibrator
            }
        }

        fun toAmplitude(intensity: Double): Int {
            val clamped = intensity.coerceIn(0.0, 1.0)
            if (clamped <= 0.0) {
                return 1
            }

            return (clamped * 255).toInt().coerceIn(1, 255)
        }

        fun numberToLong(value: Any?): Long? {
            return when (value) {
                is Number -> value.toLong()
                is String -> value.toLongOrNull()
                else -> null
            }
        }

        fun numberToDouble(value: Any?): Double? {
            return when (value) {
                is Number -> value.toDouble()
                is String -> value.toDoubleOrNull()
                else -> null
            }
        }
    }

    class Vibrate(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d(TAG, "Vibrate: Executing Vibration.Vibrate")

            val vibrator = vibrator(context)
                ?: return mapOf("success" to false, "error" to "Vibrator service unavailable")

            if (!vibrator.hasVibrator()) {
                return mapOf("success" to false, "error" to "Device does not support vibration")
            }

            val duration = (numberToLong(parameters["duration"]) ?: 100L).coerceIn(1L, 5000L)
            val intensity = numberToDouble(parameters["intensity"]) ?: 0.5
            // sharpness is iOS-only and ignored on Android
            val amplitude = toAmplitude(intensity)

            return try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    vibrator.vibrate(VibrationEffect.createOneShot(duration, amplitude))
                } else {
                    @Suppress("DEPRECATION")
                    vibrator.vibrate(duration)
                }
                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e(TAG, "Vibrate failed: ${e.message}")
                mapOf("success" to false, "error" to (e.message ?: "Unknown vibration error"))
            }
        }
    }

    class HasHaptics(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val vibrator = vibrator(context)
            val supported = vibrator?.hasVibrator() == true
            return mapOf("success" to true, "supported" to supported)
        }
    }

    class Cancel(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            return try {
                vibrator(context)?.cancel()
                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e(TAG, "Cancel failed: ${e.message}")
                mapOf("success" to false, "error" to (e.message ?: "Unknown cancel error"))
            }
        }
    }

    class PlayPattern(private val context: Context) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            Log.d(TAG, "PlayPattern: Executing Vibration.PlayPattern")

            val vibrator = vibrator(context)
                ?: return mapOf("success" to false, "error" to "Vibrator service unavailable")

            if (!vibrator.hasVibrator()) {
                return mapOf("success" to false, "error" to "Device does not support vibration")
            }

            @Suppress("UNCHECKED_CAST")
            val steps = parameters["steps"] as? List<Map<String, Any?>>
                ?: return mapOf("success" to false, "error" to "Invalid parameters. 'steps' array is required.")

            if (steps.isEmpty()) {
                return mapOf("success" to false, "error" to "Pattern steps cannot be empty")
            }

            // Build waveform: timings alternate off/on starting with delay 0
            val timings = mutableListOf<Long>()
            val amplitudes = mutableListOf<Int>()
            var pendingPause = 0L

            for (step in steps) {
                val type = step["type"] as? String ?: "vibrate"
                val duration = (numberToLong(step["duration"]) ?: 0L).coerceIn(0L, 5000L)

                if (type == "pause") {
                    pendingPause += duration
                    continue
                }

                val intensity = numberToDouble(step["intensity"]) ?: 0.5
                val amplitude = toAmplitude(intensity)

                // Off segment (pause before this vibrate), then on segment
                timings.add(pendingPause)
                amplitudes.add(0)
                timings.add(maxOf(1L, duration))
                amplitudes.add(amplitude)
                pendingPause = 0L
            }

            if (timings.isEmpty()) {
                return mapOf("success" to false, "error" to "Pattern contains no vibration steps")
            }

            return try {
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                    vibrator.vibrate(
                        VibrationEffect.createWaveform(
                            timings.toLongArray(),
                            amplitudes.toIntArray(),
                            -1
                        )
                    )
                } else {
                    @Suppress("DEPRECATION")
                    vibrator.vibrate(timings.toLongArray(), -1)
                }
                mapOf("success" to true)
            } catch (e: Exception) {
                Log.e(TAG, "PlayPattern failed: ${e.message}")
                mapOf("success" to false, "error" to (e.message ?: "Unknown pattern error"))
            }
        }
    }
}
