package com.dalifajr.jualan_listener

import android.content.Context

object ListenerConfigStore {
    private const val PREF_NAME = "listener_config"
    private const val KEY_ENDPOINT = "endpoint"
    private const val KEY_SECRET = "secret"
    private const val KEY_MONITOR_ALL = "monitor_all"
    private const val KEY_SELECTED_APPS = "selected_apps"

    fun getEndpoint(context: Context): String =
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
            .getString(KEY_ENDPOINT, "")
            .orEmpty()

    fun getSecret(context: Context): String =
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
            .getString(KEY_SECRET, "")
            .orEmpty()

    fun isMonitorAll(context: Context): Boolean =
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
            .getBoolean(KEY_MONITOR_ALL, true)

    fun getSelectedApps(context: Context): Set<String> =
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
            .getStringSet(KEY_SELECTED_APPS, emptySet())
            ?.toSet()
            ?: emptySet()

    fun setConfig(context: Context, endpoint: String, secret: String, monitorAll: Boolean) {
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
            .edit()
            .putString(KEY_ENDPOINT, endpoint.trim())
            .putString(KEY_SECRET, secret.trim())
            .putBoolean(KEY_MONITOR_ALL, monitorAll)
            .apply()
    }

    fun setSelectedApps(context: Context, packageNames: Set<String>) {
        context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)
            .edit()
            .putStringSet(KEY_SELECTED_APPS, packageNames)
            .apply()
    }
}
