import { useEffect } from 'react';
import { useForm, usePage } from '@inertiajs/react';

export default function Login() {
    const { props } = usePage();
    const deviceError = props.flash?.device_error ?? null;

    const fingerprint = typeof window !== 'undefined' ? window.deviceFingerprint?.get?.() || '' : '';
    const { data, setData, post, processing, errors } = useForm({
        email:     '',
        password:  '',
        device_id: fingerprint,
    });

    useEffect(() => {
        if (!data.device_id && window.deviceFingerprint?.get) {
            setData('device_id', window.deviceFingerprint.get() || '');
        }
    }, [data.device_id, setData]);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/auth/login', { preserveScroll: false, preserveState: false });
    };

    return (
        <div className="min-h-screen flex items-center justify-center p-4 relative overflow-hidden"
             style={{ background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)' }}>

            {/* CSS-only particles */}
            <div className="fixed inset-0 pointer-events-none overflow-hidden" aria-hidden="true">
                {Array.from({ length: 15 }).map((_, i) => (
                    <div key={i} className="particle" />
                ))}
                <div className="absolute inset-0"
                     style={{ background: 'radial-gradient(ellipse 60% 40% at 25% 35%, rgba(20,184,166,0.08) 0%, transparent 60%)' }} />
                <div className="absolute inset-0"
                     style={{ background: 'radial-gradient(ellipse 50% 40% at 75% 65%, rgba(59,130,246,0.06) 0%, transparent 60%)' }} />
            </div>

            <div className="max-w-md w-full relative z-10">
                <div className="text-center mb-8">
                    <div className="inline-block mb-6 relative">
                        <div className="absolute inset-0 bg-teal-500/20 blur-2xl rounded-full animate-slow-pulse" />
                        <img src="/asset/logo.png" alt="IEC Logo" className="w-24 h-24 mx-auto relative" />
                    </div>
                    <h1 className="text-4xl font-bold text-white mb-2 tracking-tight">IEC NERTP</h1>
                    <p className="text-gray-400 text-sm">National Elections Results &amp; Transparency Platform</p>
                    <p className="text-gray-500 text-xs mt-2">Authorized personnel only. All access is logged.</p>
                </div>

                <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-2xl p-8">
                    <h2 className="text-2xl font-bold text-gray-800 mb-6 text-center">Sign in to your account</h2>

                    {/* Device mismatch / revoked error — shown after redirect from 2FA */}
                    {deviceError && (
                        <div className="mb-4 p-4 bg-red-50 border border-red-300 rounded-lg">
                            <div className="flex items-start gap-3">
                                <svg className="w-5 h-5 text-red-600 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clipRule="evenodd" />
                                </svg>
                                <div>
                                    <p className="text-sm font-semibold text-red-800">Device Not Authorised</p>
                                    <p className="text-sm text-red-700 mt-1">{deviceError}</p>
                                </div>
                            </div>
                        </div>
                    )}

                    {(errors.email || errors.password) && (
                        <div className="mb-4 p-4 bg-red-100 border-l-4 border-red-600 text-red-800 rounded-lg text-sm">
                            <strong>⚠ Login Error</strong>
                            <p>{errors.email || errors.password}</p>
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <input type="hidden" name="device_id" value={data.device_id} />
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
                                Email address
                            </label>
                            <input id="email" type="email" value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="your.email@iec.gm"
                                required disabled={processing} autoComplete="email"
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors" />
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-2">
                                Password
                            </label>
                            <input id="password" type="password" value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                required disabled={processing} autoComplete="current-password"
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors" />
                        </div>

                        <button type="submit" disabled={processing}
                            className="w-full py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-lg transition-all shadow-lg">
                            {processing ? 'Signing in…' : 'Sign in'}
                        </button>
                    </form>

                    <div className="mt-6 text-center text-xs text-gray-500">
                        Secured with 2FA · Device Binding · Audit Logging
                    </div>
                </div>

                <div className="text-center mt-6 text-sm text-gray-400">
                    Independent Electoral Commission of The Gambia
                </div>
            </div>
        </div>
    );
}