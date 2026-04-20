import { useState, useCallback } from 'react';
import { Link, router } from '@inertiajs/react';

export default function AppLayout({ user, children }) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [isLoggingOut, setIsLoggingOut]     = useState(false);
    const isAuthenticated = !!user;

    const handleLogout = useCallback((e) => {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (isLoggingOut) return;
        setIsLoggingOut(true);
        setMobileMenuOpen(false);

        const csrfToken = document.head
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        router.post('/logout', {}, {
            headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
            onFinish: () => setIsLoggingOut(false),
            onError:  () => setIsLoggingOut(false),
        });
    }, [isLoggingOut]);

    return (
        <div className="iec-layout">

            {/* Static CSS background with particle stars — same across all pages */}
            <div className="iec-bg" aria-hidden="true">
                {/* Pure CSS particle stars — 20 particles matching login page */}
                {Array.from({ length: 30 }).map((_, i) => (
                    <div key={i} className="particle" />
                ))}
            </div>

            {/* Header */}
            <header className="iec-header">
                <div className="container mx-auto px-4 py-4">
                    <div className="flex items-center justify-between">
                        <Link href="/" className="flex items-center gap-3">
                            <img src="/asset/logo.png" alt="IEC Logo" className="w-12 h-12" />
                            <div className="text-white hidden sm:block">
                                <h1 className="text-base font-bold">Independent Electoral Commission</h1>
                                <p className="text-xs text-gray-300">The Gambia</p>
                            </div>
                        </Link>

                        {/* Desktop Nav */}
                        <nav className="hidden md:flex items-center gap-6 text-white">
                            <Link href="/" className="hover:text-pink-400 transition-colors font-medium">
                                Home
                            </Link>
                            <Link href="/results" className="hover:text-pink-400 transition-colors font-medium">
                                Results
                            </Link>
                            {isAuthenticated ? (
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    disabled={isLoggingOut}
                                    className="px-6 py-2 bg-pink-600 hover:bg-pink-700 rounded-lg font-semibold disabled:opacity-50 transition-colors flex items-center gap-2"
                                >
                                    {isLoggingOut ? 'Logging out…' : 'Logout'}
                                </button>
                            ) : (
                                <Link href="/auth/login"
                                    className="px-6 py-2 bg-gradient-to-r from-pink-600 to-pink-700 rounded-lg font-semibold hover:from-pink-700 hover:to-pink-800 transition-all">
                                    Staff Login
                                </Link>
                            )}
                        </nav>

                        {/* Mobile hamburger */}
                        <button
                            type="button"
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="md:hidden text-white p-2 rounded-lg hover:bg-white/10 transition-colors"
                            aria-label="Toggle menu"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {mobileMenuOpen
                                    ? <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    : <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                }
                            </svg>
                        </button>
                    </div>

                    {/* Mobile menu */}
                    {mobileMenuOpen && (
                        <nav className="md:hidden mt-4 pb-4 border-t border-slate-700/50 pt-4 space-y-3">
                            <Link href="/"
                                className="block text-white py-2 px-3 rounded-lg hover:bg-white/10 transition-colors"
                                onClick={() => setMobileMenuOpen(false)}>
                                Home
                            </Link>
                            <Link href="/results"
                                className="block text-white py-2 px-3 rounded-lg hover:bg-white/10 transition-colors"
                                onClick={() => setMobileMenuOpen(false)}>
                                Results
                            </Link>
                            {isAuthenticated ? (
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    disabled={isLoggingOut}
                                    className="w-full text-left px-3 py-3 bg-pink-600 hover:bg-pink-700 text-white rounded-lg font-semibold disabled:opacity-50 flex items-center gap-2 transition-colors"
                                >
                                    {isLoggingOut ? 'Logging out…' : 'Logout'}
                                </button>
                            ) : (
                                <Link href="/auth/login"
                                    className="block text-center px-6 py-3 bg-pink-600 hover:bg-pink-700 text-white rounded-lg font-semibold transition-colors"
                                    onClick={() => setMobileMenuOpen(false)}>
                                    Staff Login
                                </Link>
                            )}
                        </nav>
                    )}
                </div>
            </header>

            <main className="iec-main">{children}</main>

            <footer className="iec-footer">
                <div className="container mx-auto px-4 text-center text-gray-400">
                    <p>© 2026 Independent Electoral Commission of The Gambia</p>
                    <p className="text-sm mt-1">Fair-Play, Integrity and Transparency</p>
                </div>
            </footer>
        </div>
    );
}
