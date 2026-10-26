package com.remote.agent;

import android.os.IBinder;
import android.os.SystemClock;
import android.view.InputEvent;
import android.view.KeyEvent;
import android.view.MotionEvent;

import java.lang.reflect.Method;

/**
 * InputDispatcher: Komponen Penyuntik Input (Touch & Keyboard) Berbasis Root
 * Menggunakan Android Internal IInputManager API dengan Fallback Otomatis ke Shell Input.
 */
public class InputDispatcher {

    private Object inputManagerInstance;
    private Method injectInputEventMethod;
    private long lastDownTime = 0;
    private boolean isReflectionReady = false;

    public InputDispatcher() {
        initReflection();
    }

    /**
     * Menginisialisasi IInputManager via Java Reflection untuk performa latensi 0ms.
     */
    private void initReflection() {
        try {
            Class<?> serviceManagerClass = Class.forName("android.os.ServiceManager");
            Method getServiceMethod = serviceManagerClass.getMethod("getService", String.class);
            IBinder binder = (IBinder) getServiceMethod.invoke(null, "input");

            Class<?> stubClass = Class.forName("android.hardware.input.IInputManager$Stub");
            Method asInterfaceMethod = stubClass.getMethod("asInterface", IBinder.class);
            inputManagerInstance = asInterfaceMethod.invoke(null, binder);

            // Cari method injectInputEvent(InputEvent, int)
            Method[] methods = inputManagerInstance.getClass().getMethods();
            for (Method method : methods) {
                if (method.getName().equals("injectInputEvent")) {
                    Class<?>[] params = method.getParameterTypes();
                    if (params.length == 2 && InputEvent.class.isAssignableFrom(params[0]) && params[1] == int.class) {
                        injectInputEventMethod = method;
                        isReflectionReady = true;
                        System.out.println("[InputDispatcher] Internal IInputManager API siap digunakan.");
                        break;
                    }
                }
            }
        } catch (Throwable t) {
            System.err.println("[InputDispatcher] Gagal memuat reflection IInputManager: " + t.getMessage());
            System.err.println("[InputDispatcher] Beralih ke fallback mode Shell Input.");
            isReflectionReady = false;
        }
    }

    /**
     * Menyuntikkan Event Sentuhan Layar (Touch Event)
     * @param action MotionEvent.ACTION_DOWN, ACTION_MOVE, ACTION_UP
     * @param x Koordinat horizontal layar
     * @param y Koordinat vertikal layar
     */
    public synchronized void injectTouch(int action, float x, float y) {
        long now = SystemClock.uptimeMillis();
        if (action == MotionEvent.ACTION_DOWN) {
            lastDownTime = now;
        }

        if (isReflectionReady && injectInputEventMethod != null) {
            try {
                MotionEvent.PointerProperties[] properties = new MotionEvent.PointerProperties[1];
                properties[0] = new MotionEvent.PointerProperties();
                properties[0].id = 0;
                properties[0].toolType = MotionEvent.TOOL_TYPE_FINGER;

                MotionEvent.PointerCoords[] coords = new MotionEvent.PointerCoords[1];
                coords[0] = new MotionEvent.PointerCoords();
                coords[0].x = x;
                coords[0].y = y;
                coords[0].pressure = 1.0f;
                coords[0].size = 1.0f;

                MotionEvent event = MotionEvent.obtain(
                        lastDownTime,
                        now,
                        action,
                        1,
                        properties,
                        coords,
                        0,
                        0,
                        1.0f,
                        1.0f,
                        0,
                        0,
                        0x1002, // InputDevice.SOURCE_TOUCHSCREEN
                        0
                );

                injectInputEventMethod.invoke(inputManagerInstance, event, 0); // 0 = INJECT_INPUT_EVENT_MODE_ASYNC
                event.recycle();
                return;
            } catch (Throwable t) {
                System.err.println("[InputDispatcher] Reflection touch error: " + t.getMessage());
            }
        }

        // Fallback Shell Input jika reflection terhambat oleh versi kernel tertentu
        if (action == MotionEvent.ACTION_DOWN || action == MotionEvent.ACTION_UP) {
            fallbackShellTouch(x, y);
        }
    }

    /**
     * Menyuntikkan Tombol Navigasi / Keyboard (Key Event)
     * @param keyCode Kode tombol Android (misal: 4 untuk BACK, 3 untuk HOME)
     */
    public synchronized void injectKey(int keyCode) {
        long now = SystemClock.uptimeMillis();
        if (isReflectionReady && injectInputEventMethod != null) {
            try {
                KeyEvent downEvent = new KeyEvent(now, now, KeyEvent.ACTION_DOWN, keyCode, 0);
                KeyEvent upEvent = new KeyEvent(now, now, KeyEvent.ACTION_UP, keyCode, 0);

                injectInputEventMethod.invoke(inputManagerInstance, downEvent, 0);
                injectInputEventMethod.invoke(inputManagerInstance, upEvent, 0);
                return;
            } catch (Throwable t) {
                System.err.println("[InputDispatcher] Reflection key error: " + t.getMessage());
            }
        }

        // Fallback Shell Keyevent
        fallbackShellKey(keyCode);
    }

    /**
     * Menyuntikkan Gestur Geser (Swipe)
     */
    public void injectSwipe(float startX, float startY, float endX, float endY, int durationMs) {
        new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    injectTouch(MotionEvent.ACTION_DOWN, startX, startY);
                    int steps = Math.max(10, durationMs / 15);
                    float stepX = (endX - startX) / steps;
                    float stepY = (endY - startY) / steps;

                    for (int i = 1; i <= steps; i++) {
                        Thread.sleep(durationMs / steps);
                        injectTouch(MotionEvent.ACTION_MOVE, startX + (stepX * i), startY + (stepY * i));
                    }

                    Thread.sleep(10);
                    injectTouch(MotionEvent.ACTION_UP, endX, endY);
                } catch (InterruptedException e) {
                    injectTouch(MotionEvent.ACTION_UP, endX, endY);
                }
            }
        }).start();
    }

    /**
     * Fallback cepat eksekusi input tap via shell
     */
    private void fallbackShellTouch(float x, float y) {
        try {
            Runtime.getRuntime().exec(new String[]{"sh", "-c", "input tap " + (int)x + " " + (int)y});
        } catch (Exception ignored) {}
    }

    /**
     * Fallback cepat eksekusi keyevent via shell
     */
    private void fallbackShellKey(int keyCode) {
        try {
            Runtime.getRuntime().exec(new String[]{"sh", "-c", "input keyevent " + keyCode});
        } catch (Exception ignored) {}
    }
}
