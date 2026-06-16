import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { usePage } from '@inertiajs/react';

export default function ChangePassword() {
    const { flash } = usePage().props;
    const { data, setData, post, processing, errors } = useForm({
        password:              '',
        password_confirmation: '',
    });

    const [showPassword, setShowPassword] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/auth/change-password', { preserveScroll: true });
    };

    const strength = (() => {
        const p = data.password;
        if (!p) return null;
        let score = 0;
        if (p.length >= 8)  score++;
        if (p.length >= 12) score++;
        if (/[A-Z]/.test(p)) score++;
        if (/[0-9]/.test(p)) score++;
        if (/[^A-Za-z0-9]/.test(p)) score++;
        if (score <= 1) return { label: 'Weak',   color: 'bg-red-500',    width: '20%' };
        if (score <= 2) return { label: 'Fair',   color: 'bg-amber-500',  width: '45%' };
        if (score <= 3) return { label: 'Good',   color: 'bg-yellow-400', width: '65%' };
        if (score <= 4) return { label: 'Strong', color: 'bg-iec-pink-500', width: '85%' };
        return { label: 'Very Strong', color: 'bg-green-500', width: '100%' };
    })();

    return (
        <div
            className="min-h-screen flex items-center justify-center p-4 relative overflow-hidden"
            style={{ background: 'linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%)' }}
        >
            <div className="max-w-md w-full relative z-10">
                {/* Logo */}
                <div className="text-center mb-8">
                    <div className="inline-block mb-6 relative">
                        <img src="/asset/logo.png" alt="IEC Logo" className="w-20 h-20 mx-auto" />
                    </div>
                    <h1 className="text-3xl font-bold text-white mb-2">Set New Password</h1>
                    <p className="text-gray-400 text-sm">
                        Your account requires a new password before you can continue.
                    </p>
                </div>

                <div className="bg-white/95 backdrop-blur-sm rounded-xl shadow-2xl p-8">
                    {/* Info banner */}
                    <div className="mb-5 p-3 bg-amber-50 border border-amber-200 rounded-lg flex items-start gap-2">
                        <span className="text-amber-500 text-lg flex-shrink-0">🔒</span>
                        <p className="text-amber-700 text-sm">
                            An administrator has reset your password. Please choose a new secure password to access your dashboard.
                        </p>
                    </div>

                    {flash?.info && (
                        <div className="mb-4 p-3 bg-blue-50 border border-blue-200 rounded-lg text-blue-700 text-sm">
                            {flash.info}
                        </div>
                    )}

                    <form onSubmit={handleSubmit} className="space-y-5">
                        {/* New Password */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                New Password <span className="text-red-500">*</span>
                            </label>
                            <div className="relative">
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={data.password}
                                    onChange={(e) => setData('password', e.target.value)}
                                    placeholder="Minimum 8 characters"
                                    required
                                    disabled={processing}
                                    className="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                                />
                                <button
                                    type="button"
                                    onClick={() => setShowPassword(v => !v)}
                                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 text-sm"
                                >
                                    {showPassword ? '🙈' : '👁'}
                                </button>
                            </div>

                            {/* Strength bar */}
                            {strength && (
                                <div className="mt-2">
                                    <div className="h-1.5 w-full bg-gray-200 rounded-full overflow-hidden">
                                        <div
                                            className={`h-full rounded-full transition-all duration-300 ${strength.color}`}
                                            style={{ width: strength.width }}
                                        />
                                    </div>
                                    <p className="text-xs text-gray-500 mt-1">
                                        Strength: <span className="font-semibold">{strength.label}</span>
                                    </p>
                                </div>
                            )}

                            {errors.password && (
                                <p className="text-red-600 text-sm mt-1">{errors.password}</p>
                            )}
                        </div>

                        {/* Confirm Password */}
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                Confirm New Password <span className="text-red-500">*</span>
                            </label>
                            <input
                                type={showPassword ? 'text' : 'password'}
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                placeholder="Re-enter your new password"
                                required
                                disabled={processing}
                                className="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-colors"
                            />
                            {data.password && data.password_confirmation && data.password !== data.password_confirmation && (
                                <p className="text-red-500 text-xs mt-1">Passwords do not match.</p>
                            )}
                            {errors.password_confirmation && (
                                <p className="text-red-600 text-sm mt-1">{errors.password_confirmation}</p>
                            )}
                        </div>

                        {/* Tips */}
                        <div className="p-3 bg-gray-50 rounded-lg text-xs text-gray-500 space-y-1">
                            <p className="font-semibold text-gray-600">Password requirements:</p>
                            {[
                                [data.password.length >= 8, 'At least 8 characters'],
                                [/[A-Z]/.test(data.password), 'At least one uppercase letter'],
                                [/[0-9]/.test(data.password), 'At least one number'],
                                [/[^A-Za-z0-9]/.test(data.password), 'At least one special character'],
                            ].map(([met, label]) => (
                                <div key={label} className="flex items-center gap-2">
                                    <span className={met ? 'text-green-500' : 'text-gray-300'}>
                                        {met ? '✓' : '○'}
                                    </span>
                                    <span className={met ? 'text-green-600' : ''}>{label}</span>
                                </div>
                            ))}
                        </div>

                        <button
                            type="submit"
                            disabled={
                                processing ||
                                !data.password ||
                                data.password !== data.password_confirmation ||
                                data.password.length < 8
                            }
                            className="w-full py-3 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold rounded-lg transition-all shadow-lg"
                        >
                            {processing ? 'Updating Password…' : 'Set New Password & Continue'}
                        </button>
                    </form>
                </div>

                <div className="text-center mt-4 text-xs text-gray-500">
                    Independent Electoral Commission of The Gambia
                </div>
            </div>
        </div>
    );
}