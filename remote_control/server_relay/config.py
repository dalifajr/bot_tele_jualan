"""
Konfigurasi Relay & Gateway Server Remote Control
"""
import os

class ServerConfig:
    HOST = os.getenv("RELAY_HOST", "127.0.0.1")
    PORT = int(os.getenv("RELAY_PORT", 8099))
    
    # Token Keamanan Autentikasi
    DEVICE_AUTH_TOKEN = os.getenv("DEVICE_AUTH_TOKEN", "SECRET_REMOTE_TOKEN_2026")
    ADMIN_SECRET_KEY = os.getenv("ADMIN_SECRET_KEY", "ADMIN_SECRET_PASS_2026")
    
    # Ping & Timeout
    HEARTBEAT_INTERVAL = 15  # Detik
    CLIENT_TIMEOUT = 30      # Detik
