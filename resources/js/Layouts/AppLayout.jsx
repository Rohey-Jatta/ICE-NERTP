import { Link } from '@inertiajs/react';
import { useState } from 'react';

export default function AppLayout({ children }) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);

    return (
        <div className="min-h-screen bg-gradient-to-br from-[#0a1e3d] via-[#1e3a5f] to-[#0a1e3d]">
            {/* Animated Background */}
            <div className="fixed inset-0 overflow-hidden pointer-events-none">
                {/* Animated particles */}
                <div className="absolute inset-0 opacity-30">
                    {[...Array(50)].map((_, i) => (
                        <div
                            key={i}
                            className="absolute bg-white rounded-full animate-float"
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
                {/* Gradient orbs */}
                <div className="absolute top-20 left-20 w-96 h-96 bg-gradient-radial from-pink-500/20 to-transparent rounded-full blur-3xl animate-pulse-slow" />
                <div className="absolute bottom-20 right-20 w-96 h-96 bg-gradient-radial from-blue-500/20 to-transparent rounded-full blur-3xl animate-pulse-slow" style={{ animationDelay: '2s' }} />
            </div>

            {/* Static Header */}
            <header className="fixed top-0 left-0 right-0 z-50 bg-[#0d2847]/95 backdrop-blur-md border-b border-blue-900/50 shadow-lg">
                <div className="container mx-auto px-4 py-4">
                    <div className="flex items-center justify-between">
                        {/* Logo */}
                        <Link href="/" className="flex items-center gap-3 hover:opacity-80 transition-opacity">
                            <img
                                src="/asset/logo.png"
                                alt="IEC Logo"
                                className="w-12 h-12 object-contain"
                            />
                            <div className="text-white hidden sm:block">
                                <h1 className="text-base font-bold leading-tight">Independent Electoral Commission</h1>
                                <p className="text-xs text-gray-300">The Gambia</p>
                            </div>
                        </Link>

                        {/* Desktop Navigation */}
                        <nav className="hidden md:flex items-center gap-6 text-white">
                            <a href="/" className="hover:text-pink-500 transition-colors font-medium">
                                Home
                            </a>
                            <a href="/results" className="hover:text-pink-500 transition-colors font-medium">
                                Results
                            </a>
                            <a
                                href="/auth/login"
                                className="px-6 py-2 bg-gradient-to-r from-pink-500 to-pink-600 hover:from-pink-600 hover:to-pink-700 rounded-lg font-semibold transition-all transform hover:scale-105 shadow-lg"
                            >
                                Staff Login
                            </a>
                        </nav>

                        {/* Mobile Menu Button */}
                        <button
                            onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                            className="md:hidden text-white p-2 hover:bg-blue-900/50 rounded-lg transition-colors"
                        >
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {mobileMenuOpen ? (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                ) : (
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                )}
                            </svg>
                        </button>
                    </div>

                    {/* Mobile Menu */}
                    {mobileMenuOpen && (
                        <nav className="md:hidden mt-4 pb-4 border-t border-blue-900/50 pt-4 space-y-3 animate-slide-down">
                            <Link
                                href="/"
                                className="block text-white hover:text-pink-500 transition-colors font-medium py-2"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Home
                            </Link>
                            <Link
                                href="/results"
                                className="block text-white hover:text-pink-500 transition-colors font-medium py-2"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Results
                            </Link>
                            <Link
                                href="/auth/login"
                                className="block w-full text-center px-6 py-3 bg-gradient-to-r from-pink-500 to-pink-600 text-white rounded-lg font-semibold"
                                onClick={() => setMobileMenuOpen(false)}
                            >
                                Staff Login
                            </Link>
                        </nav>
                    )}
                </div>
            </header>

            {/* Main Content with top padding for fixed header */}
            <main className="relative z-10 pt-20">
                {children}
            </main>

            {/* Footer */}
            <footer className="relative z-10 bg-[#0a1e3d]/95 backdrop-blur-sm border-t border-blue-900/50 py-8 mt-20">
                <div className="container mx-auto px-4 text-center text-gray-400">
                    <p>© 2026 Independent Electoral Commission of The Gambia</p>
                    <p className="text-sm mt-2">Fair-Play, Integrity and Transparency</p>
                </div>
            </footer>
        </div>
    );
}
