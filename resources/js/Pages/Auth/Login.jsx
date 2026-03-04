import { useForm } from '@inertiajs/react';

export default function Login() {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        
        // Simple post - no callbacks, let Inertia handle redirect
        post('/auth/login', {
            preserveScroll: false,
            preserveState: false,
        });
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-[#1a1a2e] via-[#16213e] to-[#0f3460] flex items-center justify-center p-4 relative overflow-hidden">
            {/* SLOW SPARKLE ANIMATION BACKGROUND */}
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
                                animationDuration: `${Math.random() * 40 + 40}s`, // 40-80 seconds (VERY SLOW)
                            }}
                        />
                    ))}
                </div>
                
                {/* SLOW GLOWING ORBS */}
                <div className="absolute top-1/4 left-1/4 w-96 h-96 bg-teal-500/10 rounded-full blur-3xl animate-slow-pulse" />
                <div 
                    className="absolute bottom-1/4 right-1/4 w-96 h-96 bg-blue-500/10 rounded-full blur-3xl animate-slow-pulse" 
                    style={{ animationDelay: '4s' }} 
                />
            </div>

            <div className="max-w-md w-full relative z-10">
                <div className="text-center mb-8">
                    <div className="inline-block mb-6 relative">
                        <div className="absolute inset-0 bg-teal-500/20 blur-2xl rounded-full animate-slow-pulse" />
                        <img src="/asset/logo.png" alt="IEC Logo" className="w-24 h-24 mx-auto relative" />
                    </div>
                    <h1 className="text-4xl font-bold text-white mb-2 tracking-tight">IEC NERTP</h1>
                    <p className="text-gray-400 text-sm">National Elections Results & Transparency Platform</p>
                    <p className="text-gray-500 text-xs mt-2">Authorized personnel only. All access is logged.</p>
                </div>

                <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-2xl p-8">
                    <h2 className="text-2xl font-bold text-gray-800 mb-6 text-center">Sign in to your account</h2>

                    {/* ONLY SHOW ERROR MESSAGES */}
                    {errors.email && (
                        <div className="mb-4 p-3 bg-red-100 border border-red-300 text-red-800 rounded-lg text-sm">
                            {errors.email}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700 mb-2">
                                Email address
                            </label>
                            <input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                placeholder="your.email@iec.gm"
                                required
                                disabled={processing}
                                autoComplete="email"
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            />
                        </div>

                        <div>
                            <label htmlFor="password" className="block text-sm font-medium text-gray-700 mb-2">
                                Password
                            </label>
                            <input
                                id="password"
                                type="password"
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                placeholder="••••••••"
                                required
                                disabled={processing}
                                autoComplete="current-password"
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all"
                            />
                        </div>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:from-blue-400 disabled:to-blue-500 text-white font-semibold rounded-lg transition-all shadow-lg hover:shadow-xl disabled:cursor-not-allowed"
                        >
                            {processing ? 'Signing in...' : 'Sign in →'}
                        </button>
                    </form>

                    <div className="mt-6 text-center text-xs text-gray-500">
                        ��� Secured with 2FA · Device Binding · Audit Logging
                    </div>
                </div>

                <div className="text-center mt-6 text-sm text-gray-400">
                    Independent Electoral Commission of The Gambia
                </div>
            </div>
        </div>
    );
}
