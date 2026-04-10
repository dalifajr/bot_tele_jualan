package com.dalifajr.jualan_listener.utils

object RupiahParser {
    private val amountRegex = Regex(
        pattern = """(?i)(?:rp|idr)\s*([0-9]{1,3}(?:[.,][0-9]{3})+|[0-9]+)(?:[.,][0-9]{1,2})?"""
    )

    private val genericAmountRegex = Regex(
        pattern = """(?<![0-9])([0-9]{1,3}(?:[.,][0-9]{3})+|[0-9]{4,})(?![0-9])"""
    )

    fun parseAmount(rawText: String): Long? {
        val text = rawText.trim()
        if (text.isEmpty()) return null

        val fromRupiahKeyword = amountRegex.findAll(text)
            .mapNotNull { normalizeNumber(it.groupValues.getOrNull(1).orEmpty()) }
            .toList()

        if (fromRupiahKeyword.isNotEmpty()) {
            return fromRupiahKeyword.maxOrNull()
        }

        // Fallback if notification omits currency symbol but still likely payment-related.
        val fallback = genericAmountRegex.findAll(text)
            .mapNotNull { normalizeNumber(it.groupValues.getOrNull(1).orEmpty()) }
            .filter { it >= 1000 }
            .toList()

        return fallback.maxOrNull()
    }

    private fun normalizeNumber(raw: String): Long? {
        val digits = raw
            .replace(".", "")
            .replace(",", "")
            .trim()
        if (digits.isEmpty()) return null
        return digits.toLongOrNull()
    }
}
