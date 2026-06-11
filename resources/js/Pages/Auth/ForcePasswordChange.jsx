import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function ForcePasswordChange({ auth }) {
    const { data, setData, post, processing, errors } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });
    const [show, setShow] = useState(false);

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/auth/change-password');
    };

    const inputClass =
        'w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy outline-none focus:border-iec-pink-600';

    return (
        <div className="flex min-h-screen items-center justify-center bg-slate-50 p-6">
            <div className="w-full max-w-md">
                <div className="rounded-xl border border-slate-200 bg-white p-8">
                    <div className="mb-6 text-center">
                        <div className="mx-auto mb-3 grid h-12 w-12 place-items-center rounded-full bg-iec-pink-50 text-2xl">🔐</div>
                        <h1 className="text-2xl font-bold text-iec-navy">Set a New Password</h1>
                        <p className="mt-2 text-sm text-slate-500">
                            {auth?.user?.name ? `Hi ${auth.user.name.split(' ')[0]}, you` : 'You'} are signed in with a
                            default password. For security, choose your own password before continuing.
                        </p>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-5">
                        <div>
                            <label className="mb-2 block font-semibold text-slate-600">Current (default) password</label>
                            <input
                                type={show ? 'text' : 'password'}
                                value={data.current_password}
                                onChange={(e) => setData('current_password', e.target.value)}
                                autoComplete="current-password"
                                className={inputClass}
                                placeholder="••••••••"
                                required
                            />
                            {errors.current_password && <p className="mt-1 text-sm text-red-500">{errors.current_password}</p>}
                        </div>

                        <div>
                            <label className="mb-2 block font-semibold text-slate-600">New password</label>
                            <input
                                type={show ? 'text' : 'password'}
                                value={data.password}
                                onChange={(e) => setData('password', e.target.value)}
                                autoComplete="new-password"
                                className={inputClass}
                                placeholder="At least 8 characters"
                                required
                                minLength={8}
                            />
                            {errors.password && <p className="mt-1 text-sm text-red-500">{errors.password}</p>}
                        </div>

                        <div>
                            <label className="mb-2 block font-semibold text-slate-600">Confirm new password</label>
                            <input
                                type={show ? 'text' : 'password'}
                                value={data.password_confirmation}
                                onChange={(e) => setData('password_confirmation', e.target.value)}
                                autoComplete="new-password"
                                className={inputClass}
                                placeholder="Repeat the new password"
                                required
                                minLength={8}
                            />
                        </div>

                        <label className="flex items-center gap-2 text-sm text-slate-500">
                            <input type="checkbox" checked={show} onChange={(e) => setShow(e.target.checked)} />
                            Show passwords
                        </label>

                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-iec-pink-600 px-6 py-3 font-bold text-white transition-colors hover:bg-iec-pink-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving…' : 'Save New Password'}
                        </button>
                    </form>
                </div>
                <p className="mt-4 text-center text-xs text-slate-400">
                    You will be taken to your dashboard once your password is updated.
                </p>
            </div>
        </div>
    );
}
