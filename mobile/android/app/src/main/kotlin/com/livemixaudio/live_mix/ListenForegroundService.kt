package com.livemixaudio.live_mix

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.app.Service
import android.content.Context
import android.content.Intent
import android.content.pm.ServiceInfo
import android.net.wifi.WifiManager
import android.os.Build
import android.os.IBinder
import android.os.PowerManager
import android.util.Log
import androidx.core.app.NotificationCompat
import androidx.core.app.ServiceCompat
import androidx.core.content.ContextCompat
import java.util.concurrent.atomic.AtomicBoolean

/**
 * mediaPlayback foreground service so WHEP/WebRTC listen audio survives
 * app background / screen-off on Android 8+.
 *
 * Critical Android contract: after [ContextCompat.startForegroundService], this
 * service MUST call [startForeground] within ~5–10s on EVERY start path
 * (including Stop). Missing that call kills the process with
 * RemoteServiceException ("keeps stopping").
 *
 * Notification small icon uses a framework drawable so OEM "Bad notification"
 * crashes from app vector/mipmap icons cannot take down playback.
 *
 * Dart [stop] uses [Context.stopService] only — never startForegroundService(STOP).
 * A [stopRequested] flag covers the race where stop arrives before onStartCommand.
 */
class ListenForegroundService : Service() {
    private var wakeLock: PowerManager.WakeLock? = null
    private var wifiLock: WifiManager.WifiLock? = null
    private var startedInForeground = false

    override fun onBind(intent: Intent?): IBinder? = null

    override fun onStartCommand(intent: Intent?, flags: Int, startId: Int): Int {
        val action = intent?.action
        val shouldStop = action == ACTION_STOP || stopRequested.get()

        if (shouldStop) {
            // Satisfy any pending startForegroundService promise first.
            promoteForeground(
                title = intent?.getStringExtra(EXTRA_TITLE) ?: "Listening",
                artist = intent?.getStringExtra(EXTRA_ARTIST) ?: "Sound Mix Live",
                playing = false,
            )
            // Only notification Stop should notify Flutter — Dart already
            // tears down media when it calls stop() itself.
            if (action == ACTION_STOP) {
                try {
                    sendBroadcast(
                        Intent(ACTION_STOP_BROADCAST).setPackage(packageName),
                    )
                } catch (e: Exception) {
                    Log.w(TAG, "stop broadcast failed", e)
                }
            }
            stopRequested.set(false)
            stopSelfSafely()
            return START_NOT_STICKY
        }

        when (action) {
            ACTION_UPDATE -> {
                val title = intent.getStringExtra(EXTRA_TITLE) ?: "Listening"
                val artist = intent.getStringExtra(EXTRA_ARTIST) ?: "Sound Mix Live"
                val playing = intent.getBooleanExtra(EXTRA_PLAYING, true)
                promoteForeground(title, artist, playing)
                if (stopRequested.get()) {
                    stopSelfSafely()
                    return START_NOT_STICKY
                }
                return START_STICKY
            }
            else -> {
                val title = intent?.getStringExtra(EXTRA_TITLE) ?: "Listening"
                val artist = intent?.getStringExtra(EXTRA_ARTIST) ?: "Sound Mix Live"
                promoteForeground(title, artist, playing = true)
                if (stopRequested.get()) {
                    stopSelfSafely()
                    return START_NOT_STICKY
                }
                return START_STICKY
            }
        }
    }

    override fun onDestroy() {
        releaseLocks()
        startedInForeground = false
        super.onDestroy()
    }

    /**
     * Always attempt [startForeground] before any other work that can throw.
     * Never exit a startForegroundService delivery without a successful promote.
     */
    private fun promoteForeground(title: String, artist: String, playing: Boolean) {
        ensureChannel()

        val attempts = listOf(
            { buildNotification(title, artist, playing) },
            { buildFallbackNotification(title) },
            { buildLastResortNotification() },
        )

        for (build in attempts) {
            val notification = runCatching { build() }.getOrNull() ?: continue
            if (startForegroundSafe(notification)) {
                startedInForeground = true
                runCatching { acquireLocks() }
                return
            }
        }

        // Absolute last try — uncaught failure here is better than silent stopSelf
        // without startForeground (that guarantees RemoteServiceException).
        try {
            @Suppress("DEPRECATION")
            val bare = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
                Notification.Builder(this, CHANNEL_ID)
                    .setContentTitle("Sound Mix Live")
                    .setContentText("Listening")
                    .setSmallIcon(android.R.drawable.ic_media_play)
                    .build()
            } else {
                Notification.Builder(this)
                    .setContentTitle("Sound Mix Live")
                    .setContentText("Listening")
                    .setSmallIcon(android.R.drawable.ic_media_play)
                    .build()
            }
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                ServiceCompat.startForeground(
                    this,
                    NOTIFICATION_ID,
                    bare,
                    ServiceInfo.FOREGROUND_SERVICE_TYPE_MEDIA_PLAYBACK,
                )
            } else {
                @Suppress("DEPRECATION")
                startForeground(NOTIFICATION_ID, bare)
            }
            startedInForeground = true
            Log.w(TAG, "Promoted with bare platform Notification")
        } catch (e: Exception) {
            Log.e(TAG, "FATAL: could not startForeground — process may be killed", e)
        }
    }

    private fun startForegroundSafe(notification: Notification): Boolean {
        return try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.Q) {
                ServiceCompat.startForeground(
                    this,
                    NOTIFICATION_ID,
                    notification,
                    ServiceInfo.FOREGROUND_SERVICE_TYPE_MEDIA_PLAYBACK,
                )
            } else {
                @Suppress("DEPRECATION")
                startForeground(NOTIFICATION_ID, notification)
            }
            true
        } catch (e: Exception) {
            Log.e(TAG, "startForeground failed", e)
            false
        } catch (e: Throwable) {
            Log.e(TAG, "startForeground fatal", e)
            false
        }
    }

    private fun stopSelfSafely() {
        releaseLocks()
        try {
            if (startedInForeground) {
                ServiceCompat.stopForeground(this, ServiceCompat.STOP_FOREGROUND_REMOVE)
            }
        } catch (_: Exception) {
        }
        startedInForeground = false
        stopSelf()
    }

    private fun acquireLocks() {
        if (wakeLock?.isHeld != true) {
            val pm = getSystemService(Context.POWER_SERVICE) as PowerManager
            wakeLock = pm.newWakeLock(
                PowerManager.PARTIAL_WAKE_LOCK,
                "SoundMixLive:Listen",
            ).also {
                it.setReferenceCounted(false)
                it.acquire(4 * 60 * 60 * 1000L) // 4h max; stop() releases earlier
            }
        }
        if (wifiLock?.isHeld != true) {
            try {
                @Suppress("DEPRECATION")
                val wm = applicationContext.getSystemService(Context.WIFI_SERVICE) as WifiManager
                @Suppress("DEPRECATION")
                wifiLock = wm.createWifiLock(
                    WifiManager.WIFI_MODE_FULL_HIGH_PERF,
                    "SoundMixLive:ListenWifi",
                ).also {
                    it.setReferenceCounted(false)
                    it.acquire()
                }
            } catch (e: Exception) {
                Log.w(TAG, "WifiLock skipped: ${e.message}")
            }
        }
    }

    private fun releaseLocks() {
        try {
            if (wakeLock?.isHeld == true) wakeLock?.release()
        } catch (_: Exception) {
        }
        wakeLock = null
        try {
            if (wifiLock?.isHeld == true) wifiLock?.release()
        } catch (_: Exception) {
        }
        wifiLock = null
    }

    private fun ensureChannel() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        try {
            val nm = getSystemService(NotificationManager::class.java)
            val channel = NotificationChannel(
                CHANNEL_ID,
                "Listening",
                NotificationManager.IMPORTANCE_LOW,
            ).apply {
                description = "Keeps live audio playing in the background"
                setShowBadge(false)
            }
            nm.createNotificationChannel(channel)
        } catch (e: Exception) {
            Log.e(TAG, "createNotificationChannel failed", e)
        }
    }

    private fun buildNotification(title: String, artist: String, playing: Boolean): Notification {
        val launch = packageManager.getLaunchIntentForPackage(packageName)
        val contentIntent = PendingIntent.getActivity(
            this,
            0,
            launch,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        // Deliver STOP to the already-running service (not a new FGS start).
        val stopIntent = Intent(this, ListenForegroundService::class.java).apply {
            action = ACTION_STOP
        }
        val stopPending = PendingIntent.getService(
            this,
            1,
            stopIntent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE,
        )

        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(title.ifBlank { "Listening" })
            .setContentText(if (playing) artist else "Paused · $artist")
            .setSmallIcon(android.R.drawable.ic_media_play)
            .setContentIntent(contentIntent)
            .setOngoing(playing)
            .setOnlyAlertOnce(true)
            .setCategory(NotificationCompat.CATEGORY_TRANSPORT)
            .setVisibility(NotificationCompat.VISIBILITY_PUBLIC)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)
            .addAction(
                android.R.drawable.ic_menu_close_clear_cancel,
                "Stop",
                stopPending,
            )
            .build()
    }

    private fun buildFallbackNotification(title: String): Notification {
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle(title.ifBlank { "Sound Mix Live" })
            .setContentText("Listening")
            .setSmallIcon(android.R.drawable.ic_media_play)
            .setOngoing(true)
            .setOnlyAlertOnce(true)
            .setPriority(NotificationCompat.PRIORITY_LOW)
            .setForegroundServiceBehavior(NotificationCompat.FOREGROUND_SERVICE_IMMEDIATE)
            .build()
    }

    private fun buildLastResortNotification(): Notification {
        return NotificationCompat.Builder(this, CHANNEL_ID)
            .setContentTitle("Sound Mix Live")
            .setSmallIcon(android.R.drawable.ic_media_play)
            .setOngoing(true)
            .setPriority(NotificationCompat.PRIORITY_MIN)
            .build()
    }

    companion object {
        private const val TAG = "ListenFgService"
        const val CHANNEL_ID = "listen_playback"
        const val NOTIFICATION_ID = 7101
        const val ACTION_STOP = "com.livemixaudio.live_mix.LISTEN_STOP"
        const val ACTION_STOP_BROADCAST = "com.livemixaudio.live_mix.LISTEN_STOP_EVENT"
        const val ACTION_UPDATE = "com.livemixaudio.live_mix.LISTEN_UPDATE"
        const val EXTRA_TITLE = "title"
        const val EXTRA_ARTIST = "artist"
        const val EXTRA_PLAYING = "playing"

        /** Set by [stop] so a racing start promotes then exits without lingering. */
        private val stopRequested = AtomicBoolean(false)

        fun start(context: Context, title: String, artist: String) {
            stopRequested.set(false)
            val intent = Intent(context, ListenForegroundService::class.java).apply {
                putExtra(EXTRA_TITLE, title)
                putExtra(EXTRA_ARTIST, artist)
            }
            startFgs(context, intent, "start")
        }

        fun update(context: Context, title: String, artist: String, playing: Boolean) {
            if (stopRequested.get()) return
            val intent = Intent(context, ListenForegroundService::class.java).apply {
                action = ACTION_UPDATE
                putExtra(EXTRA_TITLE, title)
                putExtra(EXTRA_ARTIST, artist)
                putExtra(EXTRA_PLAYING, playing)
            }
            startFgs(context, intent, "update")
        }

        fun stop(context: Context) {
            // Never startForegroundService just to stop — that reopened the crash.
            stopRequested.set(true)
            try {
                val stopped = context.stopService(
                    Intent(context, ListenForegroundService::class.java),
                )
                Log.i(TAG, "stopService => $stopped (stopRequested=true for race)")
            } catch (e: Exception) {
                Log.e(TAG, "stop service failed", e)
            }
        }

        private fun startFgs(context: Context, intent: Intent, label: String) {
            try {
                ContextCompat.startForegroundService(context, intent)
            } catch (e: Exception) {
                // ForegroundServiceStartNotAllowedException etc. — caller keeps audio.
                Log.e(TAG, "$label startForegroundService failed", e)
            }
        }
    }
}
