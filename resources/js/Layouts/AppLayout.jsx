import { useState, useCallback, useEffect, useRef } from 'react';
import { Link, router } from '@inertiajs/react';

/* ─── Particle config ─────────────────────────────────────────────────────
   Module-level constant → runs ONCE on import, never re-computed.
   Uses a pseudo-random seeded sequence to give stable, spread-out positions.
───────────────────────────────────────────────────────────────────────────── */
const FLOAT_ANIMS = ['float1','float2','float3','float4','float5','float6','float7','float8'];

// Simple deterministic pseudo-random based on index seed
const rand = (seed) => Math.abs((Math.sin(seed * 127.1 + 311.7) * 43758.5453) % 1);

const PARTICLES = Array.from({ length: 45 }, (_, i) => ({
    id: i,
    x: rand(i * 3.1)  * 100,
    y: rand(i * 7.3)  * 100,
    animName:        FLOAT_ANIMS[Math.floor(rand(i * 11.7) * FLOAT_ANIMS.length)],
    duration:        15 + rand(i * 5.9)  * 10,       // 15–25 s
    delay:           -(rand(i * 2.3)     * 20),       // stagger starts
    size:            1  + rand(i * 17.1) * 2.2,       // 1–3.2 px
    twinkleDur:      1.8 + rand(i * 13.3) * 2.4,
    twinkleDelay:    rand(i * 19.7) * 3,
}));

export default function AppLayout({ user, children }) {
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [isLoggingOut, setIsLoggingOut]     = useState(false);
    const isAuthenticated = !!user;
    const bgRef = useRef(null);

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

    /* ── Shooting stars ──────────────────────────────────────────────────── */
    useEffect(() => {
        const container = bgRef.current;
        if (!container) return;

        const spawnShootingStars = () => {
            const count = Math.random() > 0.45 ? 2 : 1;
            for (let i = 0; i < count; i++) {
                const delay = i * 400;
                setTimeout(() => {
                    if (!bgRef.current) return;
                    const star = document.createElement('div');
                    star.className = 'shooting-star';
                    star.style.left   = `${5  + Math.random() * 55}%`;
                    star.style.top    = `${8  + Math.random() * 40}%`;
                    star.style.width  = `${70 + Math.random() * 60}px`;
                    star.style.transform = `rotate(${22 + Math.random() * 18}deg)`;
                    bgRef.current.appendChild(star);
                    // Clean up after animation
                    setTimeout(() => {
                        if (bgRef.current && bgRef.current.contains(star)) {
                            bgRef.current.removeChild(star);
                        }
                    }, 2000);
                }, delay);
            }
        };

        // First shooting star after 6 s, then every 60 s
        const firstTimeout = setTimeout(spawnShootingStars, 6000);
        const interval     = setInterval(spawnShootingStars, 60000);

        return () => {
            clearTimeout(firstTimeout);
            clearInterval(interval);
        };
    }, []);

    return (
        <div className="iec-layout">
            {/* ── Starfield background ─────────────────────────────────── */}
            <div className="iec-bg" aria-hidden="true" ref={bgRef}>
                {PARTICLES.map((p) => (
                    <div
                        key={p.id}
                        className="particle-star"
                        style={{
                            left:   `${p.x}%`,
                            top:    `${p.y}%`,
                            width:  `${p.size}px`,
                            height: `${p.size}px`,
                            animation: [
                                `${p.animName} ${p.duration.toFixed(1)}s linear ${p.delay.toFixed(1)}s infinite`,
                                `twinkle ${p.twinkleDur.toFixed(1)}s ease-in-out ${p.twinkleDelay.toFixed(1)}s infinite`,
                            ].join(', '),
                        }}
                    />
                ))}
            </div>

            {/* ── Header ────────────────────────────────────────────────── */}
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
                                <Link
                                    href="/auth/login"
                                    className="px-6 py-2 bg-gradient-to-r from-pink-600 to-pink-700 rounded-lg font-semibold hover:from-pink-700 hover:to-pink-800 transition-all"
                                >
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
                                <button
                                    type="button"
                                    onClick={handleLogout}
                                    disabled={isLoggingOut}
                                    className="w-full text-left px-3 py-3 bg-pink-600 hover:bg-pink-700 text-white rounded-lg font-semibold disabled:opacity-50 flex items-center gap-2 transition-colors"
                                >
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
