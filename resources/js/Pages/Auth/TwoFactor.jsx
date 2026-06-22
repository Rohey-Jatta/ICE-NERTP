import { useState, useEffect } from 'react';
import { useForm, router, usePage } from '@inertiajs/react';
import { generateDeviceFingerprint, serializeFingerprint } from '../../Utils/deviceFingerprint';

export default function TwoFactor({ expiresAt, status }) {
    const { props } = usePage();

    const { data, setData, post, processing, errors } = useForm({
        code: '',
        deviceFingerprint: '',
    });

    const computeCountdown = (expiry) => {
        if (!expiry) return 600;
        return Math.max(0, expiry - Math.floor(Date.now() / 1000));
    };

    const [countdown, setCountdown] = useState(() => computeCountdown(expiresAt));
    const [isResending, setIsResending] = useState(false);
    const [resendMessage, setResendMessage] = useState('');
    const [resendError, setResendError] = useState('');

    // ── Auto-dismiss code error after 15 s ────────────────────────────────────
    const [showCodeError, setShowCodeError] = useState(false);

    useEffect(() => {
        if (errors.code) {
            setShowCodeError(true);
            const t = setTimeout(() => setShowCodeError(false), 15000);
            return () => clearTimeout(t);
        }
    }, [errors.code]);

    // Collect device fingerprint on component mount
    useEffect(() => {
        const fingerprint = generateDeviceFingerprint();
        setData('deviceFingerprint', serializeFingerprint(fingerprint));
    }, []);

    // Update countdown whenever the server sends a fresh expiresAt (e.g. after resend)
    useEffect(() => {
        setCountdown(computeCountdown(expiresAt));
    }, [expiresAt]);

    // Reflect the flash status message returned by the resend action.
    useEffect(() => {
        if (status) {
            setResendMessage(status);
            setResendError('');
        }
    }, [status]);

    // Also pick up the status from the shared flash (success key)
    useEffect(() => {
        const flash = props.flash;
        if (flash?.success) {
            setResendMessage(flash.success);
            setResendError('');
        }
    }, [props.flash]);

    // Whenever a fresh verification-code error arrives, clear resend messages
    useEffect(() => {
        if (errors.code) {
            setResendMessage('');
            setResendError('');
        }
    }, [errors.code]);

    // Countdown timer
    useEffect(() => {
        const timer = setInterval(() => {
            setCountdown((prev) => (prev <= 0 ? 0 : prev - 1));
        }, 1000);
        return () => clearInterval(timer);
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        setResendMessage('');
        setResendError('');
        setShowCodeError(false);
        post('/auth/two-factor', {
            onError: () => {
                setData('code', '');
            },
        });
    };

    // Clear error when user starts typing a new code
    const handleCodeChange = (e) => {
        const val = e.target.value.replace(/\D/g, '').slice(0, 6);
        setData('code', val);
        if (val.length > 0) {
            setShowCodeError(false);
        }
    };

    const handleResend = (e) => {
        e.preventDefault();
        if (isResending) return;

        setIsResending(true);
        setResendMessage('');
        setResendError('');

        router.post(
            '/auth/two-factor/resend',
            {},
            {
                onSuccess: () => {
                    setCountdown(600);
                    setResendMessage('A new verification code has been sent to your phone.');
                },
                onError: () => {
                    setResendError('Failed to resend code. Please try again.');
                },
                onFinish: () => {
                    setIsResending(false);
                },
            }
        );
    };

    const minutes = Math.floor(countdown / 60);
    const seconds = countdown % 60;
    const isExpired = countdown === 0;

    return (
        <div
            className="min-h-screen bg-gradient-to-br from-[#1a1a2e] via-[#16213e] to-[#0f3460] flex items-center justify-center p-4 relative overflow-hidden"
        >
            {/* Background particles */}
            <div className="fixed inset-0 overflow-hidden pointer-events-none">
                <div className="absolute inset-0 opacity-40">
                    {[...Array(60)].map((_, i) => (
                        <div
                            key={i}
                            className="absolute bg-slate-400 rounded-full animate-float"
                            style={{
                                width: `${Math.random() * 3 + 1}px`,
                                height: `${Math.random() * 3 + 1}px`,
                                left: `${Math.random() * 100}%`,
                                top: `${Math.random() * 100}%`,
                                animationDelay: `${Math.random() * 20}s`,
                                animationDuration: `${Math.random() * 40 + 40}s`,
                            }}
                        />
                    ))}
                </div>
                <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl animate-slow-pulse" />
                <div
                    className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl animate-slow-pulse"
                    style={{ animationDelay: '4s' }}
                />
            </div>

            <div className="max-w-md w-full relative z-10">
                <div className="text-center mb-8">
                    <h1 className="text-4xl font-bold text-white mb-2">Two-Factor Authentication</h1>
                    <p className="text-gray-400 text-sm">Enter the 6-digit code sent to your phone</p>
                </div>

                <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-2xl p-8">
                    <div className="text-center mb-6">
                        <div className="text-6xl mb-4">📱</div>
                        <p className="text-gray-600 text-sm">
                            A verification code has been sent to your registered phone number
                        </p>

                        {isExpired ? (
                            <div className="mt-4 p-3 bg-red-50 rounded-lg">
                                <div className="text-sm font-medium text-red-700">
                                    Code expired — please request a new one below.
                                </div>
                            </div>
                        ) : (
                            <div className="mt-4 p-3 bg-blue-50 rounded-lg">
                                <div className="text-sm text-gray-600">Code expires in:</div>
                                <div className="text-2xl font-bold text-blue-600">
                                    {minutes}:{seconds.toString().padStart(2, '0')}
                                </div>
                            </div>
                        )}

                        {/* Success message — only shown when there's no active error */}
                        {resendMessage && !errors.code && (
                            <div className="mt-3 p-3 bg-green-50 border border-green-200 rounded-lg">
                                <p className="text-sm text-green-700">{resendMessage}</p>
                            </div>
                        )}

                        {/* Resend failure */}
                        {resendError && !errors.code && (
                            <div className="mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p className="text-sm text-red-700">{resendError}</p>
                            </div>
                        )}
                    </div>

                    {/* Code error — auto-dismisses after 15 s */}
                    {showCodeError && errors.code && (
                        <div className="mb-4 p-3 bg-red-100 border border-red-300 text-red-700 rounded-lg text-sm">
                            {errors.code}
                        </div>
                    )}

                    <form onSubmit={handleSubmit}>
                        <input type="hidden" name="deviceFingerprint" value={data.deviceFingerprint} />
                        <div className="mb-6">
                            <label className="block text-sm font-medium text-gray-700 mb-2 text-center">
                                Verification Code
                            </label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={handleCodeChange}
                                placeholder="000000"
                                maxLength="6"
                                className="w-full px-4 py-4 text-center text-3xl font-mono tracking-widest border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required
                                autoFocus
                                disabled={processing || isExpired}
                                inputMode="numeric"
                                autoComplete="one-time-code"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing || data.code.length !== 6 || isExpired}
                            className="w-full py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:from-blue-400 disabled:to-blue-500 text-white font-semibold rounded-lg transition-all shadow-lg disabled:cursor-not-allowed"
                        >
                            {processing ? 'Verifying...' : 'Verify Code'}
                        </button>
                    </form>

                    <div className="mt-6 text-center space-y-3">
                        <button
                            type="button"
                            onClick={handleResend}
                            disabled={isResending}
                            className="w-full py-2 bg-white text-blue-700 border border-blue-200 rounded-lg font-semibold hover:bg-blue-50 disabled:opacity-50 disabled:cursor-not-allowed transition-all"
                        >
                            {isResending ? 'Resending...' : 'Resend code'}
                        </button>

                        <button
                            type="button"
                            onClick={() =>
                                router.visit('/auth/login', {
                                    preserveState: false,
                                    preserveScroll: false,
                                })
                            }
                            className="w-full py-2 text-sm text-blue-600 hover:text-blue-800 underline"
                        >
                            Back to login
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}