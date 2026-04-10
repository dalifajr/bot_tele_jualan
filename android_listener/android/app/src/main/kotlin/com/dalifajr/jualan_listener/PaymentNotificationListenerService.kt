package com.dalifajr.jualan_listener

import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import com.dalifajr.jualan_listener.data.EventQueueStore
import com.dalifajr.jualan_listener.data.QueuedPaymentEvent
import com.dalifajr.jualan_listener.utils.NotificationTextExtractor
import com.dalifajr.jualan_listener.utils.RupiahParser
import com.dalifajr.jualan_listener.worker.RetryScheduler
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import java.util.UUID

class PaymentNotificationListenerService : NotificationListenerService() {
    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    override fun onNotificationPosted(sbn: StatusBarNotification?) {
        if (sbn == null) return

        val endpoint = ListenerConfigStore.getEndpoint(this)
        val secret = ListenerConfigStore.getSecret(this)
        if (endpoint.isBlank() || secret.isBlank()) {
            return
        }

        val packageName = sbn.packageName.orEmpty()
        val monitorAll = ListenerConfigStore.isMonitorAll(this)
        val selectedApps = ListenerConfigStore.getSelectedApps(this)
        if (!monitorAll && packageName !in selectedApps) {
            return
        }

        val rawText = NotificationTextExtractor.extract(sbn.notification)
        if (rawText.isBlank()) {
            return
        }

        val amount = RupiahParser.parseAmount(rawText) ?: return
        if (amount <= 0) return

        val reference = NotificationTextExtractor.extractReference(rawText)
        val event = QueuedPaymentEvent(
            id = UUID.randomUUID().toString(),
            idempotencyKey = UUID.randomUUID().toString(),
            endpoint = endpoint,
            secret = secret,
            amount = amount,
            sourceApp = packageName,
            reference = reference,
            rawText = rawText,
        )

        scope.launch {
            EventQueueStore.enqueue(applicationContext, event)
            RetryScheduler.enqueue(applicationContext)
        }
    }
}
