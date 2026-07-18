package com.nativephp.mobile.bridge.functions

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.util.Log
import androidx.core.app.ActivityCompat
import androidx.core.content.ContextCompat
import androidx.fragment.app.FragmentActivity
import com.nativephp.mobile.bridge.BridgeFunction
import com.nativephp.mobile.ui.ScannerActivity

class ScannerFunctions {

    class Scan(
        private val activity: FragmentActivity,
    ) : BridgeFunction {
        override fun execute(parameters: Map<String, Any>): Map<String, Any> {
            val prompt = parameters["prompt"] as? String ?: "Scan barcode"
            val continuous = when (val c = parameters["continuous"]) {
                is Boolean -> c
                is Number -> c.toInt() != 0
                else -> false
            }
            @Suppress("UNCHECKED_CAST")
            val formats = (parameters["formats"] as? List<*>)?.mapNotNull { it as? String }
                ?: listOf("qr")
            val sessionId = parameters["id"] as? String

            activity.runOnUiThread {
                if (ContextCompat.checkSelfPermission(activity, Manifest.permission.CAMERA)
                    != PackageManager.PERMISSION_GRANTED
                ) {
                    ActivityCompat.requestPermissions(
                        activity,
                        arrayOf(Manifest.permission.CAMERA),
                        REQUEST_CAMERA
                    )
                    // First grant: user must tap Scan again after accepting the system dialog
                    Log.w(TAG, "Camera permission requested; tap Scan again after granting")
                    return@runOnUiThread
                }

                val intent = Intent(activity, ScannerActivity::class.java).apply {
                    putExtra(ScannerActivity.EXTRA_PROMPT, prompt)
                    putExtra(ScannerActivity.EXTRA_CONTINUOUS, continuous)
                    putStringArrayListExtra(ScannerActivity.EXTRA_FORMATS, ArrayList(formats))
                    putExtra(ScannerActivity.EXTRA_SESSION_ID, sessionId)
                }
                activity.startActivity(intent)
            }

            return mapOf("success" to true, "opened" to true)
        }

        companion object {
            private const val TAG = "Scanner.Scan"
            private const val REQUEST_CAMERA = 9201
        }
    }
}
