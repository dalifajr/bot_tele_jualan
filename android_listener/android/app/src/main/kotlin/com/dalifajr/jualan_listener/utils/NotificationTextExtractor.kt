package com.dalifajr.jualan_listener.utils

import android.app.Notification

object NotificationTextExtractor {
    fun extract(notification: Notification): String {
        val extras = notification.extras ?: return ""
        val title = extras.getCharSequence(Notification.EXTRA_TITLE)?.toString().orEmpty()
        val text = extras.getCharSequence(Notification.EXTRA_TEXT)?.toString().orEmpty()
        val bigText = extras.getCharSequence(Notification.EXTRA_BIG_TEXT)?.toString().orEmpty()
        return listOf(title, text, bigText)
            .filter { it.isNotBlank() }
            .joinToString("\n")
            .trim()
    }

    fun extractReference(rawText: String): String? {
        val regex = Regex("""(?i)(?:ref|reference|trx|invoice|kode)\s*[:#-]?\s*([A-Za-z0-9-]{4,})""")
        return regex.find(rawText)?.groupValues?.getOrNull(1)
    }
}
