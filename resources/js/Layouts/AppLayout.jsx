import { useState } from 'react';
import { Link, router } from '@inertiajs/react';

export default function AppLayout({ user, children }) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const isAuthenticated = !!user;

    const handleLogout = (e) => {
        // Prevent any default behaviour and stop event bubbling
        if (e) {
            e.preventDefault();
            e.stopPropagation();
        }

        if (isLoggingOut) return;
        setIsLoggingOut(true);
        setMobileMenuOpen(false);

        // Pull CSRF token directly from the meta tag as a fallback guarantee
        const csrfToken = document.head
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content');

        router.post(
            '/logout',
            {},
            {
                headers: csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {},
                onFinish: () => setIsLoggingOut(false),
                onError:  () => setIsLoggingOut(false),
            }
        );
    };

    return (
        <div className="min-h-screen bg-gradient-to-br from-[#1a1a2e] via-[#16213e] to-[#0f3460]">

            {/* Animated background */}
            <div className="fixed inset-0 overflow-hidden pointer-events-none">
                <div className="absolute inset-0 opacity-30">
                    {[...Array(50)].map((_, i) => (
                        <div
                            key={i}
                            className="absolute bg-slate-400 rounded-full animate-float"
                            style={{
                                width: `${Math.random() * 4 + 1}px`,
                                height: `${Math.random() * 4 + 1}px`,
                                left: `${Math.random() * 100}%`,
                                top: `${Math.random() * 100}%`,
                                animationDelay: `${Math.random() * 5}s`,
                                animationDuration: `${Math.random() * 10 + 10}s`,
                            }}
                        />
                    ))}
                </div>
            </div>

            {/* Header */}
            <header className="fixed top-0 left-0 right-0 z-50 bg-[#1c3147]/95 backdrop-blur-md border-b border-slate-700/50 shadow-lg">
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
                                    className="px-6 py-2 bg-pink-600 hover:bg-pink-700 rounded-lg font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors"
                                >
                                    {isLoggingOut && (
                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                    )}
                                    {isLoggingOut ? 'Logging out…' : 'Logout'}
                                </button>
                            ) : (
                                <Link
                                    href="/auth/login"
                                    className="px-6 py-2 bg-gradient-to-r from-pink-600 to-pink-700 rounded-lg font-semibold hover:from-pink-700 hover:to-pink-800 transition-all"
                                >
                                    Staff Login
                                </Link>
                            )}
                        </nav>

                        {/* Mobile hamburger toggle */}
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
                            <Link
                                href="/"
                                className="block text-white py-2 px-3 rounded-lg hover:bg-white/10 transition-colors"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Home
                            </Link>
                            <Link
                                href="/results"
                                className="block text-white py-2 px-3 rounded-lg hover:bg-white/10 transition-colors"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Results
                            </Link>
                            {isAuthenticated ? (
                                /*
                                 * Use a plain <button> — NOT an <a> or <Link>.
                                 * This guarantees no navigation happens before
                                 * the POST completes, preventing a race that
                                 * causes the 419 on slow mobile connections.
                                 */
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    disabled={isLoggingOut}
                                    className="w-full text-left px-3 py-3 bg-pink-600 hover:bg-pink-700 text-white rounded-lg font-semibold disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-2 transition-colors"
                                >
                                    {isLoggingOut && (
                                        <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4" />
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z" />
                                        </svg>
                                    )}
                                    {isLoggingOut ? 'Logging out…' : 'Logout'}
                                </button>
                            ) : (
                                <Link
                                    href="/auth/login"
                                    className="block text-center px-6 py-3 bg-pink-600 hover:bg-pink-700 text-white rounded-lg font-semibold transition-colors"
                                    onClick={() => setMobileMenuOpen(false)}
                                >
                                    Staff Login
                                </Link>
                            )}
                        </nav>
                    )}
                </div>
            </header>

            <main className="relative z-10 pt-20">{children}</main>

            <footer className="relative z-10 bg-[#1c3147]/95 border-t border-slate-700/50 py-8 mt-20">
                <div className="container mx-auto px-4 text-center text-gray-400">
                    <p>© 2026 Independent Electoral Commission of The Gambia</p>
                    <p className="text-sm mt-2">Fair-Play, Integrity and Transparency</p>
                </div>
            </footer>
        </div>
    );
}