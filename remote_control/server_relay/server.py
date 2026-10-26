"""
Server Relay & WebSocket Gateway untuk Android Remote Control
FastAPI + Asynchronous WebSockets
"""
import os
import json
import asyncio
from fastapi import FastAPI, WebSocket, WebSocketDisconnect, Query, Header, HTTPException, status
from fastapi.middleware.cors import CORSMiddleware
from fastapi.staticfiles import StaticFiles
from fastapi.responses import FileResponse, JSONResponse

from config import ServerConfig
from session_manager import session_manager

app = FastAPI(
    title="Android Root Remote Control Relay Gateway",
    description="High-performance binary and control multiplexer for Android root remote control.",
    version="1.0.0"
)

# Aktifkan CORS untuk controller lintas origin/web dashboard
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ==========================================
# REST API ENDPOINTS
# ==========================================

@app.get("/api/health")
async def health_check():
    return {
        "status": "healthy",
        "active_devices": len(session_manager.devices)
    }

@app.get("/api/devices")
async def list_devices():
    """Mengembalikan daftar semua perangkat Android yang sedang terhubung online"""
    return {
        "devices": session_manager.get_all_devices()
    }

@app.get("/api/devices/{device_id}")
async def get_device_detail(device_id: str):
    """Mendapatkan detail status spesifik satu perangkat"""
    device = session_manager.get_device(device_id)
    if not device:
        raise HTTPException(status_code=404, detail="Perangkat tidak ditemukan atau sedang offline")
    return device

# ==========================================
# WEBSOCKET ENDPOINT: ANDROID ROOT AGENT
# ==========================================

@app.websocket("/ws/device")
async def websocket_device_endpoint(
    websocket: WebSocket,
    x_device_id: str = Header(None),
    x_device_token: str = Header(None)
):
    """
    Koneksi masuk dari Daemon Agen Android Root.
    Menerima binary frame video H.264 & text event (misal shell output).
    """
    await websocket.accept()
    device_id = x_device_id or "device_unknown"
    is_registered = False

    try:
        while True:
            # Terima pesan baik Binary (video frame) maupun Text (JSON control/event)
            message = await websocket.receive()
            
            if "bytes" in message and message["bytes"]:
                # Binary Frame: Video H.264 NAL Unit langsung di-multiplex ke controllers
                if is_registered:
                    await session_manager.forward_video_frame(device_id, message["bytes"])

            elif "text" in message and message["text"]:
                raw_text = message["text"]
                try:
                    payload = json.loads(raw_text)
                    msg_type = payload.get("type")

                    if msg_type == "register":
                        device_id = payload.get("device_id", device_id)
                        token = payload.get("device_token", x_device_token or "")
                        name = payload.get("device_name", "Android Root Device")
                        width = int(payload.get("screen_width", 720))
                        height = int(payload.get("screen_height", 1280))

                        # Verifikasi token perangkat jika dikonfigurasi
                        if ServerConfig.DEVICE_AUTH_TOKEN and token != ServerConfig.DEVICE_AUTH_TOKEN:
                            await websocket.send_text(json.dumps({
                                "type": "auth_error",
                                "message": "Autentikasi token perangkat tidak valid."
                            }))
                            await websocket.close(code=1008)
                            return

                        await session_manager.register_device(
                            device_id=device_id,
                            ws=websocket,
                            name=name,
                            width=width,
                            height=height
                        )
                        is_registered = True
                        
                        await websocket.send_text(json.dumps({
                            "type": "registered",
                            "status": "success",
                            "device_id": device_id
                        }))

                    elif msg_type == "shell_output":
                        # Meneruskan output baris terminal ke controller
                        await session_manager.forward_event_to_controllers(device_id, raw_text)

                except json.JSONDecodeError:
                    pass

    except WebSocketDisconnect:
        print(f"[Gateway] Perangkat {device_id} terputus (WebSocketDisconnect).")
    except Exception as e:
        print(f"[Gateway] Kesalahan pada koneksi perangkat {device_id}: {e}")
    finally:
        if is_registered:
            await session_manager.unregister_device(device_id)

# ==========================================
# WEBSOCKET ENDPOINT: CONTROLLER (WEB & APP)
# ==========================================

@app.websocket("/ws/controller")
async def websocket_controller_endpoint(
    websocket: WebSocket,
    device_id: str = Query(...),
    token: str = Query(None)
):
    """
    Koneksi masuk dari Pengendali (Web Admin Dashboard / Aplikasi Android Pengendali).
    Menerima perintah sentuh, tombol, swipe, shell dan mengirim frame video real-time.
    """
    # Verifikasi token controller
    if ServerConfig.ADMIN_SECRET_KEY and token != ServerConfig.ADMIN_SECRET_KEY:
        await websocket.accept()
        await websocket.send_text(json.dumps({
            "type": "error",
            "message": "Token otorisasi pengendali tidak valid."
        }))
        await websocket.close(code=1008)
        return

    await websocket.accept()

    session = await session_manager.attach_controller(device_id, websocket)
    if not session:
        await websocket.send_text(json.dumps({
            "type": "error",
            "message": f"Perangkat {device_id} saat ini tidak terhubung/offline."
        }))
        await websocket.close(code=1000)
        return

    # Kirim info resolusi dan state perangkat ke controller
    await websocket.send_text(json.dumps({
        "type": "device_info",
        "device": session.to_dict()
    }))

    try:
        while True:
            raw_text = await websocket.receive_text()
            # Meneruskan perintah kontrol (touch, key, swipe, shell) langsung ke perangkat target
            await session_manager.forward_control_to_device(websocket, raw_text)

    except WebSocketDisconnect:
        print(f"[Gateway] Controller terputus dari perangkat {device_id}.")
    except Exception as e:
        print(f"[Gateway] Kesalahan pada koneksi controller {device_id}: {e}")
    finally:
        await session_manager.detach_controller(websocket)

# ==========================================
# STATIC FILES & WEB DASHBOARD MOUNTING
# ==========================================

static_dir = os.path.join(os.path.dirname(__file__), "static")
if not os.path.exists(static_dir):
    os.makedirs(static_dir, exist_ok=True)

app.mount("/static", StaticFiles(directory=static_dir), name="static")

@app.get("/")
async def root_dashboard():
    index_path = os.path.join(static_dir, "index.html")
    if os.path.exists(index_path):
        return FileResponse(index_path)
    return JSONResponse({
        "name": "Android Remote Control Relay Server",
        "status": "Running",
        "api_docs": "/docs",
        "dashboard": "static/index.html"
    })

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        "server:app",
        host=ServerConfig.HOST,
        port=ServerConfig.PORT,
        reload=False,
        log_level="info"
    )
