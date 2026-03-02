import { useState, useRef, useEffect } from "react";
import { router } from "@inertiajs/react";

export default function TwoFactor({ phone_hint, is_locked_out }) {
    const [digits, setDigits] = useState(["", "", "", "", "", ""]);
    const [message, setMessage] = useState("");
    const [isError, setIsError] = useState(false);
    const [isLockedOut, setIsLockedOut] = useState(is_locked_out);
    const [cooldown, setCooldown] = useState(0);
    const [processing, setProcessing] = useState(false);
    const refs = useRef([]);

    useEffect(() => {
        refs.current[0]?.focus();
    }, []);

    useEffect(() => {
        if (cooldown > 0) {
            const timer = setTimeout(() => setCooldown(c => c - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [cooldown]);

    function handleChange(i, val) {
        const d = val.replace(/\D/g, "").slice(-1);
        const next = [...digits];
        next[i] = d;
        setDigits(next);
        if (d && i < 5) refs.current[i + 1]?.focus();
        if (d && i === 5) {
            const code = [...next.slice(0, 5), d].join("");
            if (code.length === 6) submit(code);
        }
    }

    function handleKeyDown(i, e) {
        if (e.key === "Backspace" && !digits[i] && i > 0) {
            refs.current[i - 1]?.focus();
        }
    }

    function handlePaste(e) {
        e.preventDefault();
        const p = e.clipboardData.getData("text").replace(/\D/g, "").slice(0, 6);
        if (p.length === 6) {
            setDigits(p.split(""));
            submit(p);
        }
    }

    async function submit(code) {
        if (processing) return;
        setProcessing(true);
        setMessage("");
        setIsError(false);

        try {
            const r = await fetch("/auth/two-factor/verify", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ code }),
            });
            const d = await r.json();

            if (r.ok) {
                if (d.status === "device_registration_required") {
                    router.visit("/auth/device/register");
                } else if (d.status === "authenticated") {
                    window.location.href = d.redirect_url;
                }
            } else {
                setIsError(true);
                setDigits(["", "", "", "", "", ""]);
                refs.current[0]?.focus();
                if (r.status === 429 && d.locked_out) {
                    setIsLockedOut(true);
                }
                setMessage(d.message || "Invalid code.");
            }
        } catch {
            setIsError(true);
            setMessage("Network error.");
        } finally {
            setProcessing(false);
        }
    }

    async function resend() {
        if (cooldown > 0) return;
        const r = await fetch("/auth/two-factor/resend", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content,
            },
        });
        const d = await r.json();
        setMessage(d.message);
        setIsError(d.status === "failed");
        if (d.status === "sent") {
            setCooldown(60);
            setDigits(["", "", "", "", "", ""]);
            refs.current[0]?.focus();
        }
    }

    return (
        <div className="min-h-screen bg-gray-50 flex flex-col justify-center py-12 sm:px-6 lg:px-8">
            <div className="sm:mx-auto sm:w-full sm:max-w-md">
                <div className="flex justify-center mb-4">
                    <img
                        src="/asset/logo.png"
                        alt="IEC Logo"
                        className="w-16 h-16 object-contain rounded-full flex items-center"
                    />
                </div>
                <h1 className="text-center text-2xl font-bold text-blue-800">Verification Required</h1>
                <p className="mt-2 text-center text-sm text-gray-600">
                    Enter the 6-digit code sent to <span className="font-medium">{phone_hint}</span>
                </p>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div className="bg-white py-8 px-4 shadow-lg sm:rounded-lg sm:px-10 border border-gray-200">
                    {isLockedOut ? (
                        <div className="text-center">
                            <p className="text-lg font-medium text-red-800 mb-2">Account Temporarily Locked</p>
                            <p className="text-sm text-red-600 mb-4">{message}</p>
                            <button
                                onClick={() => router.visit("/auth/login")}
                                className="text-sm text-blue-900 underline"
                            >
                                Return to login
                            </button>
                        </div>
                    ) : (
                        <>
                            {message && (
                                <div
                                    className={`mb-4 p-3 rounded-md border ${
                                        isError
                                            ? "bg-red-50 border-red-200 text-red-500"
                                            : "bg-green-50 border-green-200 text-green-500"
                                    }`}
                                >
                                    <p className="text-sm">{message}</p>
                                </div>
                            )}

                            <div className="flex gap-3 justify-center mb-6" onPaste={handlePaste}>
                                {digits.map((d, i) => (
                                    <input
                                        key={i}
                                        ref={(el) => (refs.current[i] = el)}
                                        type="text"
                                        inputMode="numeric"
                                        maxLength={1}
                                        value={d}
                                        onChange={(e) => handleChange(i, e.target.value)}
                                        onKeyDown={(e) => handleKeyDown(i, e)}
                                        disabled={processing}
                                        className={`w-12 h-14 text-center text-2xl font-bold border-2 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors disabled:opacity-50 ${
                                            isError
                                                ? "border-red-400 bg-red-50"
                                                : d
                                                ? "border-blue-500 bg-blue-50"
                                                : "border-gray-300 bg-white"
                                        }`}
                                    />
                                ))}
                            </div>

                            <button
                                onClick={() => submit(digits.join(""))}
                                disabled={processing || digits.join("").length !== 6}
                                className="w-full py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-700 hover:bg-blue-800 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                            >
                                {processing ? "Verifying..." : "Verify Code"}
                            </button>

                            <div className="mt-4 flex justify-between text-sm">
                                <button
                                    onClick={() => router.visit("/auth/login")}
                                    className="text-gray-500 hover:text-gray-700"
                                >
                                    ← Back
                                </button>
                                <button
                                    onClick={resend}
                                    disabled={cooldown > 0}
                                    className="text-blue-900 hover:text-blue-700 disabled:text-gray-400"
                                >
                                    {cooldown > 0 ? `Resend in ${cooldown}s` : "Resend code"}
                                </button>
                            </div>
                        </>
                    )}
                </div>
            </div>
        </div>
    );
}
