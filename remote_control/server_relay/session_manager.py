"""
SessionManager: Pengelola State Perangkat & Routing Real-Time
Menghubungkan Perangkat Target (Host) dengan Pengendali (Controller)
"""
import time
import json
import asyncio
from typing import Dict, Set, Optional
from fastapi import WebSocket

class DeviceSession:
    def __init__(self, device_id: str, ws: WebSocket, device_name: str, width: int, height: int):
        self.device_id = device_id
        self.ws = ws
        self.device_name = device_name
        self.screen_width = width
        self.screen_height = height
        self.is_streaming = False
        self.connected_at = time.time()
        self.last_seen = time.time()
        self.controllers: Set[WebSocket] = set()
        self.cached_header: Optional[bytes] = None  # Cache SPS/PPS untuk inisiasi decoder instan

    def to_dict(self):
        return {
            "device_id": self.device_id,
            "device_name": self.device_name,
            "screen_width": self.screen_width,
            "screen_height": self.screen_height,
            "is_streaming": self.is_streaming,
            "controllers_count": len(self.controllers),
            "connected_at": time.strftime('%Y-%m-%d %H:%M:%S', time.localtime(self.connected_at)),
            "uptime_seconds": int(time.time() - self.connected_at)
        }

class SessionManager:
    def __init__(self):
        # device_id -> DeviceSession
        self.devices: Dict[str, DeviceSession] = {}
        # ws -> device_id (untuk controller lookup)
        self.controller_to_device: Dict[WebSocket, str] = {}
        self.lock = asyncio.Lock()

    async def register_device(self, device_id: str, ws: WebSocket, name: str, width: int, height: int) -> DeviceSession:
        async with self.lock:
            # Jika device lama dengan ID sama masih ada, tutup koneksi sebelumnya
            if device_id in self.devices:
                old_session = self.devices[device_id]
                try:
                    await old_session.ws.close(code=1000, reason="Replaced by new connection")
                except Exception:
                    pass

            session = DeviceSession(device_id, ws, name, width, height)
            self.devices[device_id] = session
            print(f"[SessionManager] Perangkat terdaftar: {device_id} ({name}) - {width}x{height}")
            return session

    async def unregister_device(self, device_id: str):
        async with self.lock:
            if device_id in self.devices:
                session = self.devices.pop(device_id)
                # Notifikasi controller bahwa device telah offline
                disconnect_msg = json.dumps({
                    "type": "device_disconnected",
                    "device_id": device_id,
                    "message": "Perangkat Android telah terputus."
                })
                for ctrl in list(session.controllers):
                    try:
                        await ctrl.send_text(disconnect_msg)
                    except Exception:
                        pass
                    self.controller_to_device.pop(ctrl, None)
                print(f"[SessionManager] Perangkat terputus: {device_id}")

    async def attach_controller(self, device_id: str, ctrl_ws: WebSocket) -> Optional[DeviceSession]:
        async with self.lock:
            session = self.devices.get(device_id)
            if not session:
                return None

            session.controllers.add(ctrl_ws)
            self.controller_to_device[ctrl_ws] = device_id

            # Kirim sinyal start_stream ke device
            session.is_streaming = True
            try:
                await session.ws.send_text(json.dumps({"type": "start_stream"}))
                print(f"[SessionManager] Mengirim start_stream ke device {device_id}")
            except Exception as e:
                print(f"[SessionManager] Gagal mengirim start_stream: {e}")

            # Jika sudah ada header SPS/PPS dari stream sebelumnya, kirimkan langsung ke controller baru
            if session.cached_header:
                try:
                    await ctrl_ws.send_bytes(session.cached_header)
                except Exception:
                    pass

            print(f"[SessionManager] Controller terhubung ke {device_id}. Total controller: {len(session.controllers)}")
            return session

    async def detach_controller(self, ctrl_ws: WebSocket):
        async with self.lock:
            device_id = self.controller_to_device.pop(ctrl_ws, None)
            if not device_id:
                return

            session = self.devices.get(device_id)
            if session:
                session.controllers.discard(ctrl_ws)
                print(f"[SessionManager] Controller terputus dari {device_id}. Sisa controller: {len(session.controllers)}")

                # Jika tidak ada controller lagi yang menonton, hentikan streaming video untuk hemat baterai & kuota
                if len(session.controllers) == 0 and session.is_streaming:
                    session.is_streaming = False
                    try:
                        await session.ws.send_text(json.dumps({"type": "stop_stream"}))
                        print(f"[SessionManager] Mengirim stop_stream ke device {device_id} (Sleep Mode)")
                    except Exception as e:
                        print(f"[SessionManager] Gagal mengirim stop_stream: {e}")

    async def forward_video_frame(self, device_id: str, frame_data: bytes):
        session = self.devices.get(device_id)
        if not session or not session.controllers:
            return

        # Deteksi dan simpan NAL Unit SPS/PPS (Codec Config) untuk inisiasi instan controller baru
        if b'\x00\x00\x00\x01\x67' in frame_data or b'\x00\x00\x01\x67' in frame_data:
            session.cached_header = frame_data

        # Broadcast video frame ke seluruh controller aktif secara paralel
        tasks = []
        for ctrl in list(session.controllers):
            tasks.append(self._safe_send_bytes(ctrl, frame_data))
        if tasks:
            await asyncio.gather(*tasks, return_exceptions=True)

    async def forward_control_to_device(self, ctrl_ws: WebSocket, message: str):
        # Tangani heartbeat ping dari controller tanpa meneruskannya ke device
        try:
            parsed = json.loads(message)
            if parsed.get("type") == "ping":
                await ctrl_ws.send_text(json.dumps({"type": "pong", "time": time.time()}))
                return
        except Exception:
            pass

        device_id = self.controller_to_device.get(ctrl_ws)
        if not device_id:
            return

        session = self.devices.get(device_id)
        if session and session.ws:
            try:
                await session.ws.send_text(message)
            except Exception as e:
                print(f"[SessionManager] Gagal mengirim kontrol ke device {device_id}: {e}")

    async def forward_event_to_controllers(self, device_id: str, message: str):
        session = self.devices.get(device_id)
        if not session or not session.controllers:
            return

        for ctrl in list(session.controllers):
            try:
                await ctrl.send_text(message)
            except Exception:
                pass

    async def _safe_send_bytes(self, ws: WebSocket, data: bytes):
        try:
            await ws.send_bytes(data)
        except Exception:
            pass

    def get_all_devices(self):
        return [session.to_dict() for session in self.devices.values()]

    def get_device(self, device_id: str) -> Optional[dict]:
        session = self.devices.get(device_id)
        return session.to_dict() if session else None

# Singleton global instance
session_manager = SessionManager()
