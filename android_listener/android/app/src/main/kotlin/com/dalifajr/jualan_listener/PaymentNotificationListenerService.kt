package com.dalifajr.jualan_listener

import android.service.notification.NotificationListenerService
import android.service.notification.StatusBarNotification
import android.util.Log
import com.dalifajr.jualan_listener.data.EventQueueStore
import com.dalifajr.jualan_listener.data.QueuedPaymentEvent
import com.dalifajr.jualan_listener.network.ListenerApiClient
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

        val endpoints = ListenerConfigStore.getActiveEndpoints(this)
        val secret = ListenerConfigStore.getSecret(this)
        if (endpoints.isEmpty() || secret.isBlank()) {
            return
        }

        val packageName = sbn.packageName.orEmpty()
        if (!SupportedPaymentApps.isSupported(packageName)) {
            return
        }

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
        val idempotencyKey = UUID.randomUUID().toString()

        scope.launch {
            var queuedRetry = false
            endpoints.forEach { endpoint ->
                val event = QueuedPaymentEvent(
                    id = UUID.randomUUID().toString(),
                    idempotencyKey = idempotencyKey,
                    endpoint = endpoint,
                    secret = secret,
                    amount = amount,
                    sourceApp = packageName,
                    reference = reference,
                    rawText = rawText,
                )
                val result = ListenerApiClient.sendPaymentEvent(event)
                if (!result.isSuccess) {
                    Log.w(TAG, "Direct send failed for $endpoint: ${result.error}")
                    if (result.shouldRetry && EventQueueStore.enqueue(applicationContext, event)) {
                        queuedRetry = true
                    }
                }
            }
            if (queuedRetry) {
                RetryScheduler.enqueue(applicationContext)
            }
        }
    }

    companion object {
        private const val TAG = "PaymentNotifListener"
    }
}
