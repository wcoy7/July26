package com.nativephp.mobile.ui

import android.os.Bundle
import android.util.Log
import android.view.ViewGroup
import android.widget.Button
import android.widget.FrameLayout
import android.widget.TextView
import androidx.appcompat.app.AppCompatActivity
import androidx.camera.core.CameraSelector
import androidx.camera.core.ImageAnalysis
import androidx.camera.core.Preview
import androidx.camera.lifecycle.ProcessCameraProvider
import androidx.camera.view.PreviewView
import androidx.core.content.ContextCompat
import com.google.mlkit.vision.barcode.BarcodeScannerOptions
import com.google.mlkit.vision.barcode.BarcodeScanning
import com.google.mlkit.vision.barcode.common.Barcode
import com.google.mlkit.vision.common.InputImage
import com.nativephp.mobile.utils.NativeActionCoordinator
import org.json.JSONObject
import java.util.concurrent.Executors
import java.util.concurrent.atomic.AtomicBoolean

/**
 * Full-screen barcode/QR scanner using CameraX + ML Kit.
 */
class ScannerActivity : AppCompatActivity() {

    private lateinit var previewView: PreviewView
    private val analysisExecutor = Executors.newSingleThreadExecutor()
    private val emitted = AtomicBoolean(false)

    private var continuous = false
    private var sessionId: String? = null
    private var prompt: String = "Scan barcode"
    private var formats: List<String> = listOf("qr")

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        prompt = intent.getStringExtra(EXTRA_PROMPT) ?: prompt
        continuous = intent.getBooleanExtra(EXTRA_CONTINUOUS, false)
        sessionId = intent.getStringExtra(EXTRA_SESSION_ID)
        formats = intent.getStringArrayListExtra(EXTRA_FORMATS)?.toList() ?: formats

        val root = FrameLayout(this).apply {
            layoutParams = ViewGroup.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            )
            setBackgroundColor(0xFF000000.toInt())
        }

        previewView = PreviewView(this).apply {
            layoutParams = FrameLayout.LayoutParams(
                ViewGroup.LayoutParams.MATCH_PARENT,
                ViewGroup.LayoutParams.MATCH_PARENT
            )
        }
        root.addView(previewView)

        val label = TextView(this).apply {
            text = prompt
            setTextColor(0xFFFFFFFF.toInt())
            textSize = 16f
            setPadding(48, 96, 48, 32)
        }
        root.addView(label)

        val close = Button(this).apply {
            text = "Close"
            setOnClickListener { finish() }
        }
        val lp = FrameLayout.LayoutParams(
            ViewGroup.LayoutParams.WRAP_CONTENT,
            ViewGroup.LayoutParams.WRAP_CONTENT
        ).apply {
            gravity = android.view.Gravity.BOTTOM or android.view.Gravity.CENTER_HORIZONTAL
            bottomMargin = 96
        }
        root.addView(close, lp)

        setContentView(root)
        startCamera()
    }

    private fun startCamera() {
        val providerFuture = ProcessCameraProvider.getInstance(this)
        providerFuture.addListener({
            val provider = providerFuture.get()
            val preview = Preview.Builder().build().also {
                it.surfaceProvider = previewView.surfaceProvider
            }

            val options = BarcodeScannerOptions.Builder()
                .setBarcodeFormats(*mapFormats(formats))
                .build()
            val scanner = BarcodeScanning.getClient(options)

            val analysis = ImageAnalysis.Builder()
                .setBackpressureStrategy(ImageAnalysis.STRATEGY_KEEP_ONLY_LATEST)
                .build()

            analysis.setAnalyzer(analysisExecutor) { imageProxy ->
                val mediaImage = imageProxy.image
                if (mediaImage == null) {
                    imageProxy.close()
                    return@setAnalyzer
                }

                val image = InputImage.fromMediaImage(
                    mediaImage,
                    imageProxy.imageInfo.rotationDegrees
                )

                scanner.process(image)
                    .addOnSuccessListener { barcodes ->
                        val code = barcodes.firstOrNull { !it.rawValue.isNullOrBlank() }
                        if (code != null) {
                            onBarcode(code)
                        }
                    }
                    .addOnCompleteListener {
                        imageProxy.close()
                    }
            }

            try {
                provider.unbindAll()
                provider.bindToLifecycle(
                    this,
                    CameraSelector.DEFAULT_BACK_CAMERA,
                    preview,
                    analysis
                )
            } catch (e: Exception) {
                Log.e(TAG, "Camera bind failed", e)
            }
        }, ContextCompat.getMainExecutor(this))
    }

    private fun onBarcode(barcode: Barcode) {
        val value = barcode.rawValue ?: return
        if (!continuous && !emitted.compareAndSet(false, true)) {
            return
        }
        if (continuous && !emitted.compareAndSet(false, true)) {
            return
        }

        val format = formatName(barcode.format)
        dispatchScan(value, format)

        if (!continuous) {
            finish()
        } else {
            // cooldown
            previewView.postDelayed({ emitted.set(false) }, 1200)
        }
    }

    private fun dispatchScan(data: String, format: String) {
        val payload = JSONObject()
            .put("data", data)
            .put("format", format)
        if (!sessionId.isNullOrBlank()) {
            payload.put("id", sessionId)
        }

        // Must dispatch via MainActivity so the WebView receives CodeScanned
        val host = MainActivity.instance
        if (host == null) {
            Log.e(TAG, "MainActivity unavailable; cannot dispatch CodeScanned")
            return
        }

        try {
            NativeActionCoordinator.dispatchEvent(
                host,
                "Native\\Mobile\\Events\\Scanner\\CodeScanned",
                payload.toString()
            )
            Log.i(TAG, "CodeScanned data=$data format=$format")
        } catch (e: Exception) {
            Log.e(TAG, "Failed to dispatch CodeScanned", e)
        }
    }

    private fun mapFormats(formats: List<String>): IntArray {
        if (formats.any { it.equals("all", true) }) {
            return intArrayOf(Barcode.FORMAT_ALL_FORMATS)
        }
        val list = mutableListOf<Int>()
        for (f in formats) {
            when (f.lowercase()) {
                "qr" -> list.add(Barcode.FORMAT_QR_CODE)
                "ean13" -> list.add(Barcode.FORMAT_EAN_13)
                "ean8" -> list.add(Barcode.FORMAT_EAN_8)
                "code128" -> list.add(Barcode.FORMAT_CODE_128)
                "code39" -> list.add(Barcode.FORMAT_CODE_39)
                "upca" -> list.add(Barcode.FORMAT_UPC_A)
                "upce" -> list.add(Barcode.FORMAT_UPC_E)
            }
        }
        if (list.isEmpty()) list.add(Barcode.FORMAT_QR_CODE)
        return list.toIntArray()
    }

    private fun formatName(format: Int): String = when (format) {
        Barcode.FORMAT_QR_CODE -> "qr"
        Barcode.FORMAT_EAN_13 -> "ean13"
        Barcode.FORMAT_EAN_8 -> "ean8"
        Barcode.FORMAT_CODE_128 -> "code128"
        Barcode.FORMAT_CODE_39 -> "code39"
        Barcode.FORMAT_UPC_A -> "upca"
        Barcode.FORMAT_UPC_E -> "upce"
        else -> "unknown"
    }

    override fun onDestroy() {
        analysisExecutor.shutdown()
        super.onDestroy()
    }

    companion object {
        private const val TAG = "ScannerActivity"
        const val EXTRA_PROMPT = "prompt"
        const val EXTRA_CONTINUOUS = "continuous"
        const val EXTRA_FORMATS = "formats"
        const val EXTRA_SESSION_ID = "session_id"
    }
}
