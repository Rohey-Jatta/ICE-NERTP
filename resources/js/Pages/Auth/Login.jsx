import { useState } from "react";
import { router } from "@inertiajs/react";

export default function Login() {
    const [email, setEmail] = useState("");
    const [password, setPassword] = useState("");
    const [processing, setProcessing] = useState(false);
    const [serverMessage, setServerMessage] = useState("");

    async function handleSubmit(e) {
        e.preventDefault();
        if (processing) return;

        setProcessing(true);
        setServerMessage("");

        try {
            const response = await fetch("/auth/login", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content,
                },
                body: JSON.stringify({ email, password }),
            });

            const data = await response.json();

            if (response.ok && data.status === "two_factor_required") {
                router.visit("/auth/two-factor");
            } else {
                setServerMessage(data.message || "Login failed. Please try again.");
            }
        } catch (error) {
            setServerMessage("Network error. Please check your connection.");
        } finally {
            setProcessing(false);
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
                <h1 className="text-center text-3xl font-bold text-blue-900">IEC NERTP</h1>
                <p className="mt-2 text-center text-sm text-gray-600">
                    National Elections Results & Transparency Platform
                </p>
                <p className="mt-1 text-center text-xs text-gray-500">
                    Authorized personnel only. All access is logged.
                </p>
            </div>

            <div className="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
                <div className="bg-white py-8 px-4 shadow-lg sm:rounded-lg sm:px-10 border border-gray-200">
                    <h2 className="mb-6 text-center text-xl font-semibold text-gray-900">
                        Sign in to your account
                    </h2>

                    {serverMessage && (
                        <div className="mb-4 p-3 bg-red-50 border border-red-200 rounded-md">
                            <p className="text-sm text-red-700">{serverMessage}</p>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                                Email address
                            </label>
                            <input
                                id="email"
                                type="email"
                                required
                                value={email}
                                onChange={(e) => setEmail(e.target.value)}
                                className="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="your.name@iec.gm"
                            />
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700">
                                Password
                            </label>
                            <input
                                id="password"
                                type="password"
                                required
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                className="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                placeholder="Enter your password"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-700 hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                        >
                            {processing ? "Signing in..." : "Sign in →"}
                        </button>
                    </form>

                    <div className="mt-6 pt-4 border-t border-gray-100">
                        <p className="text-xs text-center text-gray-400">
                             Secured with 2FA · Device Binding · Audit Logging
                        </p>
                    </div>
                </div>

                <p className="mt-4 text-center text-xs text-gray-400">
                    Independent Electoral Commission of The Gambia
                </p>
            </div>
        </div>
    );
}
