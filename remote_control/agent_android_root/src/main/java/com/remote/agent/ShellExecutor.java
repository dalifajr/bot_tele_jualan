package com.remote.agent;

import java.io.BufferedReader;
import java.io.InputStreamReader;

/**
 * ShellExecutor: Eksekusi Perintah Terminal Linux Root dari Jarak Jauh
 */
public class ShellExecutor {

    public interface OutputCallback {
        void onOutput(String outputLine, boolean isError);
    }

    /**
     * Menjalankan perintah shell root secara asinkron dan mengirimkan output baris per baris
     */
    public static void executeCommandAsync(String command, OutputCallback callback) {
        new Thread(new Runnable() {
            @Override
            public void run() {
                try {
                    Process process = Runtime.getRuntime().exec(new String[]{"sh", "-c", command});
                    
                    BufferedReader stdInput = new BufferedReader(new InputStreamReader(process.getInputStream()));
                    BufferedReader stdError = new BufferedReader(new InputStreamReader(process.getErrorStream()));

                    String s;
                    while ((s = stdInput.readLine()) != null) {
                        if (callback != null) callback.onOutput(s, false);
                    }

                    while ((s = stdError.readLine()) != null) {
                        if (callback != null) callback.onOutput(s, true);
                    }

                    process.waitFor();
                } catch (Exception e) {
                    if (callback != null) {
                        callback.onOutput("[Shell Error] " + e.getMessage(), true);
                    }
                }
            }
        }).start();
    }

    /**
     * Menjalankan perintah cepat satu baris secara sinkron
     */
    public static String executeCommandSync(String command) {
        StringBuilder result = new StringBuilder();
        try {
            Process process = Runtime.getRuntime().exec(new String[]{"sh", "-c", command});
            BufferedReader reader = new BufferedReader(new InputStreamReader(process.getInputStream()));
            String line;
            while ((line = reader.readLine()) != null) {
                result.append(line).append("\n");
            }
            process.waitFor();
        } catch (Exception e) {
            return "[Error: " + e.getMessage() + "]";
        }
        return result.toString().trim();
    }
}
