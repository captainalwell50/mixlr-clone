package com.livemixaudio.live_mix

import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.IntentFilter
import android.hardware.Sensor
import android.hardware.SensorEvent
import android.hardware.SensorEventListener
import android.hardware.SensorManager
import android.media.AudioAttributes
import android.media.AudioDeviceInfo
import android.media.AudioFocusRequest
import android.media.AudioManager
import android.os.Build
import android.os.Handler
import android.os.Looper
import android.util.Log
import io.flutter.embedding.android.FlutterActivity
import io.flutter.embedding.engine.FlutterEngine
import io.flutter.plugin.common.EventChannel
import io.flutter.plugin.common.MethodChannel

/**
 * Hosts:
 * 1) Listen foreground-service method/event channels (background audio)
 * 2) Audio-route channel that forces WHEP listen to the **loudspeaker**
 *
 * Prefer media playback routing (MODE_NORMAL + USAGE_MEDIA). Communication /
 * voice-call mode defaults to the earpiece on many OEM phones even when
 * isSpeakerphoneOn reports true.
 */
class MainActivity : FlutterActivity() {
    private var focusRequest: AudioFocusRequest? = null
    private var proximityListener: SensorEventListener? = null
    private val mainHandler = Handler(Looper.getMainLooper())
    private val reassertRunnables = mutableListOf<Runnable>()

    private var eventSink: EventChannel.EventSink? = null
    private var receiverRegistered = false

    private val stopReceiver = object : BroadcastReceiver() {
        override fun onReceive(context: Context?, intent: Intent?) {
            if (intent?.action == ListenForegroundService.ACTION_STOP_BROADCAST) {
                eventSink?.success("stop")
            }
        }
    }

    override fun configureFlutterEngine(flutterEngine: FlutterEngine) {
        super.configureFlutterEngine(flutterEngine)

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            CHANNEL,
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "start" -> {
                    try {
                        val title = call.argument<String>("title") ?: "Listening"
                        val artist = call.argument<String>("artist") ?: "Sound Mix Live"
                        ListenForegroundService.start(this, title, artist)
                        // Always report success to Dart — audio must continue even
                        // if the OEM rejects the foreground service later.
                        result.success(true)
                    } catch (e: Exception) {
                        Log.e(TAG, "listen_fg start failed", e)
                        result.success(false)
                    }
                }
                "update" -> {
                    try {
                        val title = call.argument<String>("title") ?: "Listening"
                        val artist = call.argument<String>("artist") ?: "Sound Mix Live"
                        val playing = call.argument<Boolean>("playing") ?: true
                        ListenForegroundService.update(this, title, artist, playing)
                        result.success(true)
                    } catch (e: Exception) {
                        Log.e(TAG, "listen_fg update failed", e)
                        result.success(false)
                    }
                }
                "stop" -> {
                    try {
                        ListenForegroundService.stop(this)
                        result.success(true)
                    } catch (e: Exception) {
                        Log.e(TAG, "listen_fg stop failed", e)
                        result.success(false)
                    }
                }
                else -> result.notImplemented()
            }
        }

        EventChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            EVENTS,
        ).setStreamHandler(object : EventChannel.StreamHandler {
            override fun onListen(arguments: Any?, events: EventChannel.EventSink?) {
                eventSink = events
            }

            override fun onCancel(arguments: Any?) {
                eventSink = null
            }
        })

        // Keep registered while the Flutter engine lives so notification Stop
        // works even when the activity is backgrounded.
        if (!receiverRegistered) {
            val filter = IntentFilter(ListenForegroundService.ACTION_STOP_BROADCAST)
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                applicationContext.registerReceiver(
                    stopReceiver,
                    filter,
                    Context.RECEIVER_NOT_EXPORTED,
                )
            } else {
                @Suppress("UnspecifiedRegisterReceiverFlag")
                applicationContext.registerReceiver(stopReceiver, filter)
            }
            receiverRegistered = true
        }

        MethodChannel(
            flutterEngine.dartExecutor.binaryMessenger,
            AUDIO_ROUTE,
        ).setMethodCallHandler { call, result ->
            when (call.method) {
                "preparePlayback" -> result.success(forceLoudspeaker(scheduleReassert = true))
                "forceSpeaker" -> result.success(forceLoudspeaker(scheduleReassert = false))
                "releasePlayback" -> {
                    releasePlayback()
                    result.success(true)
                }
                else -> result.notImplemented()
            }
        }
    }

    override fun cleanUpFlutterEngine(flutterEngine: FlutterEngine) {
        if (receiverRegistered) {
            try {
                applicationContext.unregisterReceiver(stopReceiver)
            } catch (_: Exception) {
            }
            receiverRegistered = false
        }
        eventSink = null
        super.cleanUpFlutterEngine(flutterEngine)
    }

    /**
     * Route listen audio to the builtin loudspeaker via media playback path.
     *
     * Layered strategy (OEM phones ignore single levers):
     * 1) MODE_NORMAL + STREAM_MUSIC (not MODE_IN_COMMUNICATION)
     * 2) isSpeakerphoneOn = true
     * 3) API 31+ clear + setCommunicationDevice(BUILTIN_SPEAKER)
     * 4) Disable proximity-driven earpiece behaviour
     * 5) Optional short re-assert schedule (WebRTC / AudioSwitch often reset route)
     */
    private fun forceLoudspeaker(scheduleReassert: Boolean): Map<String, Any> {
        return try {
            val am = getSystemService(Context.AUDIO_SERVICE) as AudioManager

            // Media/listen path — NOT voice-call. Communication mode is what
            // pins many devices to the earpiece receiver.
            am.mode = AudioManager.MODE_NORMAL
            @Suppress("DEPRECATION")
            am.isSpeakerphoneOn = true

            var deviceType = "speakerphone_flag"
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                try {
                    am.clearCommunicationDevice()
                } catch (_: Exception) {
                }

                val speaker = am.availableCommunicationDevices.firstOrNull {
                    it.type == AudioDeviceInfo.TYPE_BUILTIN_SPEAKER
                }
                if (speaker != null) {
                    val ok = try {
                        am.setCommunicationDevice(speaker)
                    } catch (_: Exception) {
                        false
                    }
                    deviceType = if (ok) "builtin_speaker" else "setCommunicationDevice_failed"
                    Log.i(TAG, "setCommunicationDevice(BUILTIN_SPEAKER)=$ok")
                } else {
                    deviceType = "no_builtin_speaker_device"
                    Log.w(TAG, "No TYPE_BUILTIN_SPEAKER in availableCommunicationDevices")
                }

                // Keep media mode after any communication-device selection.
                am.mode = AudioManager.MODE_NORMAL
                @Suppress("DEPRECATION")
                am.isSpeakerphoneOn = true
            }

            disableProximityRouting()
            requestMediaFocus(am)
            ensureStreamAudible(am, AudioManager.STREAM_MUSIC)

            if (scheduleReassert) {
                scheduleSpeakerReassert()
            }

            @Suppress("DEPRECATION")
            val speakerOn = am.isSpeakerphoneOn
            val mode = am.mode
            Log.i(
                TAG,
                "forceLoudspeaker mode=$mode speakerOn=$speakerOn device=$deviceType",
            )
            mapOf(
                "ok" to true,
                "speakerOn" to speakerOn,
                "mode" to mode,
                "device" to deviceType,
                "strategy" to "media",
            )
        } catch (e: Exception) {
            Log.e(TAG, "forceLoudspeaker failed", e)
            mapOf("ok" to false, "error" to (e.message ?: "unknown"))
        }
    }

    /** WebRTC / AudioSwitch often flip route after ICE — re-assert briefly. */
    private fun scheduleSpeakerReassert() {
        cancelSpeakerReassert()
        val delaysMs = longArrayOf(250L, 750L, 1500L, 3000L, 5000L)
        for (delay in delaysMs) {
            val r = Runnable {
                try {
                    forceLoudspeaker(scheduleReassert = false)
                } catch (e: Exception) {
                    Log.w(TAG, "reassert failed: ${e.message}")
                }
            }
            reassertRunnables.add(r)
            mainHandler.postDelayed(r, delay)
        }
    }

    private fun cancelSpeakerReassert() {
        for (r in reassertRunnables) {
            mainHandler.removeCallbacks(r)
        }
        reassertRunnables.clear()
    }

    /**
     * Hold the proximity sensor so OEM "phone near ear → earpiece" call
     * behaviour is less likely to steal listen audio. Combined with MODE_NORMAL
     * we are not an in-call app.
     */
    private fun disableProximityRouting() {
        try {
            val sm = getSystemService(Context.SENSOR_SERVICE) as SensorManager
            val proximity = sm.getDefaultSensor(Sensor.TYPE_PROXIMITY) ?: return
            if (proximityListener == null) {
                proximityListener = object : SensorEventListener {
                    override fun onSensorChanged(event: SensorEvent?) {
                        // Ignore — never switch listen audio to the earpiece.
                    }

                    override fun onAccuracyChanged(sensor: Sensor?, accuracy: Int) {}
                }
            }
            sm.unregisterListener(proximityListener)
            sm.registerListener(
                proximityListener,
                proximity,
                SensorManager.SENSOR_DELAY_NORMAL,
            )
        } catch (e: Exception) {
            Log.w(TAG, "disableProximityRouting ignored: ${e.message}")
        }
    }

    private fun requestMediaFocus(am: AudioManager) {
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                val attrs = AudioAttributes.Builder()
                    .setUsage(AudioAttributes.USAGE_MEDIA)
                    .setContentType(AudioAttributes.CONTENT_TYPE_MUSIC)
                    .build()
                val req = AudioFocusRequest.Builder(AudioManager.AUDIOFOCUS_GAIN)
                    .setAudioAttributes(attrs)
                    .setOnAudioFocusChangeListener { /* keep media session */ }
                    .build()
                focusRequest = req
                am.requestAudioFocus(req)
            } else {
                @Suppress("DEPRECATION")
                am.requestAudioFocus(
                    null,
                    AudioManager.STREAM_MUSIC,
                    AudioManager.AUDIOFOCUS_GAIN,
                )
            }
        } catch (e: Exception) {
            Log.w(TAG, "requestMediaFocus ignored: ${e.message}")
        }
    }

    private fun ensureStreamAudible(am: AudioManager, stream: Int) {
        val vol = am.getStreamVolume(stream)
        if (vol == 0) {
            val max = am.getStreamMaxVolume(stream)
            am.setStreamVolume(stream, (max * 0.6).toInt().coerceAtLeast(1), 0)
        }
    }

    private fun releasePlayback() {
        cancelSpeakerReassert()
        try {
            val sm = getSystemService(Context.SENSOR_SERVICE) as SensorManager
            proximityListener?.let { sm.unregisterListener(it) }
            proximityListener = null
        } catch (_: Exception) {
        }
        try {
            val am = getSystemService(Context.AUDIO_SERVICE) as AudioManager
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                am.clearCommunicationDevice()
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                focusRequest?.let { am.abandonAudioFocusRequest(it) }
            } else {
                @Suppress("DEPRECATION")
                am.abandonAudioFocus(null)
            }
            focusRequest = null
            @Suppress("DEPRECATION")
            am.isSpeakerphoneOn = false
            am.mode = AudioManager.MODE_NORMAL
        } catch (_: Exception) {
            // best-effort
        }
    }

    companion object {
        private const val TAG = "SmlAudioRoute"
        private const val CHANNEL = "com.livemixaudio.live_mix/listen_fg"
        private const val EVENTS = "com.livemixaudio.live_mix/listen_fg_events"
        private const val AUDIO_ROUTE = "com.livemixaudio.live_mix/audio_route"
    }
}
