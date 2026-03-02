import { useState } from "react";
import { router } from "@inertiajs/react";

export default function DeviceVerification({ device_info }) {
    const [deviceName, setDeviceName] = useState(
        `${device_info.type === "tablet" ? "Tablet" : device_info.type === "mobile" ? "Phone" : "Computer"} - ${device_info.os}`
    );
    const [processing, setProcessing] = useState(false);
    const [error, setError] = useState("");

    async function handleRegister(e) {
        e.preventDefault();
        if (!deviceName.trim()) return;

        setProcessing(true);
        setError("");

        try {
            const r = await fetch("/auth/device/register", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ device_name: deviceName }),
            });

            const d = await r.json();

            if (r.ok && d.status === "authenticated") {
                window.location.href = d.redirect_url;
            } else {
                setError(d.message || "Registration failed.");
            }
        } catch {
            setError("Network error. Please try again.");
        } finally {
            setProcessing(false);
        }
    }

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
            <div className="sm:mx-auto sm:w-full sm:max-w-md">
                <div className="flex justify-center mb-4">
                    <div className="w-16 h-16 bg-blue-900 rounded-full flex items-center justify-center">
                        <img src="/build/assets/logo.png" alt="logo" />
                    </div>
                </div>
                <h1 className="text-center text-2xl font-bold text-blue-900">New Device Detected</h1>
                <p className="mt-2 text-center text-sm text-gray-600">
                    Register this device to complete your login
                </p>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div className="bg-white py-8 px-4 shadow-lg sm:rounded-lg sm:px-10 border border-gray-200">
                    <div className="p-4 bg-blue-50 rounded-lg border border-blue-100 mb-6">
                        <p className="text-sm font-semibold text-blue-900 mb-2">Detected Device</p>
                        {[
                            ["Type", device_info.type],
                            ["OS", device_info.os],
                            ["Browser", device_info.browser],
                            ["IP", device_info.ip],
                        ].map(([k, v]) => (
                            <p key={k} className="text-xs text-gray-600">
                                <span className="font-medium">{k}:</span>{" "}
                                <span className="capitalize">{v}</span>
                            </p>
                        ))}
                    </div>

                    <div className="p-3 bg-amber-50 rounded-md border border-amber-200 mb-6">
                        <p className="text-xs text-amber-700">
                            ⚠️ Only register devices that belong to you. This device will be linked to
                            your IEC account and logged for security auditing.
                        </p>
                    </div>

                    {error && (
                        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p className="text-sm text-red-700">{error}</p>
                        </div>
                    )}

                    <form onSubmit={handleRegister} className="space-y-4">
                        <div>
                            <label htmlFor="device_name" className="block text-sm font-medium text-gray-700">
                                Device Name
                            </label>
                            <input
                                id="device_name"
                                type="text"
                                required
                                maxLength={100}
                                value={deviceName}
                                onChange={(e) => setDeviceName(e.target.value)}
                                placeholder="e.g. My IEC Tablet, Office Desktop"
                                className="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing || !deviceName.trim()}
                            className="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-900 hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {processing ? "Registering..." : "Register This Device & Continue"}
                        </button>
                    </form>

                    <div className="mt-4 text-center">
                        <button
                            onClick={() => router.visit("/auth/login")}
                            className="text-sm text-gray-500 hover:text-gray-700"
                        >
                            This is not my device — cancel login
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
