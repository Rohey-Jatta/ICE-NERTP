import { useState, useEffect } from 'react';
import { useForm, router } from '@inertiajs/react';

export default function TwoFactor({ expiresAt, status }) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
    });

    const initialCountdown = expiresAt ? Math.max(0, expiresAt - Math.floor(Date.now() / 1000)) : 600;
    const [countdown, setCountdown] = useState(initialCountdown);
    const [isResending, setIsResending] = useState(false);
    const [resendMessage, setResendMessage] = useState(status || '');

    useEffect(() => {
        const timer = setInterval(() => {
            setCountdown((prev) => {
                if (prev <= 0) {
                    return 0;
                }
                return prev - 1;
            });
        }, 1000);
        return () => clearInterval(timer);
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/auth/two-factor');
    };

    const handleResend = (e) => {
        e.preventDefault();

        if (isResending) return;

        setIsResending(true);
        router.post('/auth/two-factor/resend', {}, {
            onSuccess: () => {
                setCountdown(600);
            },
            onFinish: () => {
                setIsResending(false);
            },
        });
    };


    const minutes = Math.floor(countdown / 60);
    const seconds = countdown % 60;

    return (
        <div className="min-h-screen bg-gradient-to-br from-[#1a1a2e] via-[#16213e] to-[#0f3460] flex items-center justify-center p-4 relative overflow-hidden">

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
                                animationDuration: `${Math.random() * 40 + 40}s`, // 40-80 seconds
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
                        <div className="text-6xl mb-4"></div>
                        <p className="text-gray-600 text-sm">
                            A verification code has been sent to your registered phone number
                        </p>
                        <div className="mt-4 p-3 bg-blue-50 rounded-lg">
                            <div className="text-sm text-gray-600">Code expires in:</div>
                            <div className="text-2xl font-bold text-blue-600">
                                {minutes}:{seconds.toString().padStart(2, '0')}
                            </div>
                        </div>

                        {resendMessage}

                       
                    </div>

                    {errors.code && (
                        <div className="mb-4 p-3 bg-red-100 border border-red-300 text-red-400 rounded-lg text-sm">
                            {errors.code}
                        </div>
                    )}

                    <form onSubmit={handleSubmit}>
                        <div className="mb-6">
                            <label className="block text-sm font-medium text-gray-700 mb-2 text-center">
                                Verification Code
                            </label>
                            <input
                                type="text"
                                value={data.code}
                                onChange={(e) => setData('code', e.target.value.replace(/\D/g, '').slice(0, 6))}
                                placeholder="000000"
                                maxLength="6"
                                className="w-full px-4 py-4 text-center text-3xl font-mono tracking-widest border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required
                                autoFocus
                                disabled={processing}
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing || data.code.length !== 6}
                            className="w-full py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:from-blue-400 disabled:to-blue-500 text-white font-semibold rounded-lg transition-all shadow-lg"
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
                            onClick={() => router.visit('/auth/login', { preserveState: false, preserveScroll: false })}
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
