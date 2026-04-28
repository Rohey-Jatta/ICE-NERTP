import { useState, useCallback, useEffect } from 'react';
import { Link, router, usePage } from '@inertiajs/react';
import useInertiaPrefetch from '@/Hooks/useInertiaPrefetch';

// ── Social icon paths ──────────────────────────────────────────────────────
const SocialIcon = ({ name }) => {
    const paths = {
        twitter: 'M23.953 4.57a10 10 0 01-2.825.775 4.958 4.958 0 002.163-2.723c-.951.555-2.005.959-3.127 1.184a4.92 4.92 0 00-8.384 4.482C7.69 8.095 4.067 6.13 1.64 3.162a4.822 4.822 0 00-.666 2.475c0 1.71.87 3.213 2.188 4.096a4.904 4.904 0 01-2.228-.616v.06a4.923 4.923 0 003.946 4.827 4.996 4.996 0 01-2.212.085 4.936 4.936 0 004.604 3.417 9.867 9.867 0 01-6.102 2.105c-.39 0-.779-.023-1.17-.067a13.995 13.995 0 007.557 2.209c9.053 0 13.998-7.496 13.998-13.985 0-.21 0-.42-.015-.63A9.935 9.935 0 0024 4.59z',
        facebook: 'M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z',
        instagram: 'M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z',
        linkedin: 'M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433c-1.144 0-2.063-.926-2.063-2.065 0-1.138.92-2.063 2.063-2.063 1.14 0 2.064.925 2.064 2.063 0 1.139-.925 2.065-2.064 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z',
        youtube: 'M23.495 6.205a3.007 3.007 0 00-2.088-2.088c-1.87-.501-9.396-.501-9.396-.501s-7.507-.01-9.396.501A3.007 3.007 0 00.527 6.205a31.247 31.247 0 00-.522 5.805 31.247 31.247 0 00.522 5.783 3.007 3.007 0 002.088 2.088c1.868.502 9.396.502 9.396.502s7.506 0 9.396-.502a3.007 3.007 0 002.088-2.088 31.247 31.247 0 00.5-5.783 31.247 31.247 0 00-.5-5.805zM9.609 15.601V8.408l6.264 3.602z',
    };
    return (
        <svg className="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path d={paths[name]} />
        </svg>
    );
};

const SOCIALS = [
    { name: 'twitter',   href: 'https://twitter.com/IECGambia',             label: 'Twitter'   },
    { name: 'facebook',  href: 'https://facebook.com/IECGambia',            label: 'Facebook'  },
    { name: 'instagram', href: 'https://instagram.com/IECGambia',           label: 'Instagram' },
    { name: 'linkedin',  href: 'https://linkedin.com/company/IEC-Gambia',   label: 'LinkedIn'  },
    { name: 'youtube',   href: 'https://youtube.com/@IECGambia',            label: 'YouTube'   },
];

const FOOTER_LINKS = [
    { href: '/',                  label: 'Home'             },
    { href: '/results',           label: 'Public Results'   },
    { href: '/results/map',       label: 'Results Map'      },
    { href: '/results/stations',  label: 'Polling Stations' },
    { href: '/auth/login',        label: 'Staff Login'      },
];

const SECURITY_ITEMS = [
    { icon: '🔐', text: 'Multi-factor authentication' },
    { icon: '📍', text: 'GPS-validated submissions'   },
    { icon: '📋', text: 'Full audit trail'            },
    { icon: '🔒', text: 'Device binding security'    },
    { icon: '✅', text: 'Sequential certification'   },
];

// ── Component ──────────────────────────────────────────────────────────────
export default function AppLayout({ children }) {
    const { auth }         = usePage().props;
    const user             = auth?.user;
    const isAuthenticated  = !!user;

    const [mobileOpen,  setMobileOpen]  = useState(false);
    const [isLoggingOut, setIsLoggingOut] = useState(false);
    const [scrolled,    setScrolled]    = useState(false);

    useInertiaPrefetch([], { global: true });

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 4);
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const handleLogout = useCallback((e) => {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (isLoggingOut) return;
        setIsLoggingOut(true);
        setMobileOpen(false);

        const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content;
        router.post('/logout', {}, {
            headers: csrf ? { 'X-CSRF-TOKEN': csrf } : {},
            onFinish: () => setIsLoggingOut(false),
            onError:  () => setIsLoggingOut(false),
        });
    }, [isLoggingOut]);

    return (
        <div className="min-h-screen flex flex-col bg-[#0f172a]">

            {/* ══ IEC Pink Top Info Bar ═══════════════════════════════════ */}
            <div className="iec-top-bar hidden sm:block">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between">

                        {/* Left: Contact details */}
                        <div className="flex items-center gap-5 text-white/90">
                            {/* Email */}
                            <a href="mailto:admin@iec.gm"
                               className="flex items-center gap-1.5 hover:text-white transition-colors">
                                <svg className="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                    <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                </svg>
                                <span className="text-xs">admin@iec.gm</span>
                            </a>

                            {/* Address */}
                            <span className="hidden lg:flex items-center gap-1.5 text-xs opacity-90">
                                <svg className="w-3.5 h-3.5 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fillRule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd"/>
                                </svg>
                                Election House, Bertil Harding Highway, Kanifing East Layout, The Gambia
                            </span>
                        </div>

                        {/* Right: Social media icons */}
                        <div className="flex items-center gap-2.5">
                            {SOCIALS.map(s => (
                                <a key={s.name} href={s.href} aria-label={s.label}
                                   target="_blank" rel="noopener noreferrer"
                                   className="text-white/75 hover:text-white transition-colors">
                                    <SocialIcon name={s.name} />
                                </a>
                            ))}
                        </div>

                    </div>
                </div>
            </div>

            {/* ══ Main Navigation Header ═══════════════════════════════════ */}
            <header className={`iec-nav-header ${scrolled ? 'is-scrolled' : ''}`}>
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                    <div className="flex items-center justify-between h-[4.25rem]">

                        {/* Logo + Wordmark */}
                        <Link href="/" prefetch className="flex items-center gap-3 shrink-0 group">
                            <img src="/asset/logo.png" alt="IEC Logo"
                                 className="w-11 h-11 object-contain transition-transform group-hover:scale-105" />
                            <div className="hidden sm:block leading-tight">
                                <p className="text-[0.8rem] font-bold text-gray-900 leading-snug">
                                    Independent Electoral Commission
                                </p>
                                <p className="text-[0.65rem] text-gray-400 leading-snug tracking-wide">
                                    National Elections Results &amp; Transparency Platform
                                </p>
                            </div>
                        </Link>

                        {/* Desktop Navigation */}
                        <nav className="hidden md:flex items-center gap-7">
                            <Link href="/" prefetch className="nav-link">Home</Link>
                            <Link href="/results"          className="nav-link">Results</Link>
                            <Link href="/results/map"      className="nav-link">Map</Link>
                            <Link href="/results/stations" className="nav-link">Stations</Link>

                            <div className="w-px h-5 bg-gray-200 mx-1" />

                            {isAuthenticated ? (
                                <div className="flex items-center gap-3">
                                    <div className="hidden lg:flex flex-col items-end">
                                        <span className="text-xs font-semibold text-gray-700 leading-none">
                                            {user.name}
                                        </span>
                                        <span className="text-[0.65rem] text-gray-400 mt-0.5 leading-none capitalize">
                                            {user.roles?.[0]?.name?.replace(/-/g,' ') ?? 'Staff'}
                                        </span>
                                    </div>
                                    <button type="button" onClick={handleLogout} disabled={isLoggingOut}
                                        className="btn-iec btn-iec-primary">
                                        {isLoggingOut ? 'Signing out…' : 'Sign Out'}
                                    </button>
                                </div>
                            ) : (
                                <Link href="/auth/login" className="btn-iec btn-iec-primary">
                                    Staff Login
                                </Link>
                            )}
                        </nav>

                        {/* Mobile Hamburger */}
                        <button type="button"
                            onClick={() => setMobileOpen(v => !v)}
                            className="md:hidden p-2 text-gray-500 hover:text-gray-900 hover:bg-gray-50 rounded-lg transition-colors"
                            aria-label="Toggle navigation" aria-expanded={mobileOpen}>
                            <svg className="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                {mobileOpen
                                    ? <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                    : <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 6h16M4 12h16M4 18h16" />
                                }
                            </svg>
                        </button>
                    </div>

                    {/* Mobile Dropdown Menu */}
                    {mobileOpen && (
                        <nav className="md:hidden border-t border-gray-100 pt-3 pb-4 space-y-0.5">
                            {[
                                { href: '/',                  label: 'Home',             prefetch: true  },
                                { href: '/results',           label: 'Public Results',   prefetch: false },
                                { href: '/results/map',       label: 'Results Map',      prefetch: false },
                                { href: '/results/stations',  label: 'Polling Stations', prefetch: false },
                            ].map(link => (
                                <Link key={link.href} href={link.href}
                                    className="flex items-center px-3 py-2.5 text-sm font-medium text-gray-700 hover:bg-pink-50 hover:text-iec-pink-500 rounded-lg transition-colors"
                                    onClick={() => setMobileOpen(false)}
                                    {...(link.prefetch ? { prefetch: true } : {})}>
                                    {link.label}
                                </Link>
                            ))}

                            <div className="border-t border-gray-100 my-2 mx-3" />

                            {isAuthenticated ? (
                                <>
                                    <div className="px-3 py-2">
                                        <p className="text-xs text-gray-400">Signed in as</p>
                                        <p className="text-sm font-semibold text-gray-700">{user.name}</p>
                                    </div>
                                    <div className="px-3">
                                        <button type="button" onClick={handleLogout} disabled={isLoggingOut}
                                            className="w-full btn-iec btn-iec-primary justify-center">
                                            {isLoggingOut ? 'Signing out…' : 'Sign Out'}
                                        </button>
                                    </div>
                                </>
                            ) : (
                                <div className="px-3">
                                    <Link href="/auth/login"
                                        className="btn-iec btn-iec-primary w-full justify-center"
                                        onClick={() => setMobileOpen(false)}>
                                        Staff Login
                                    </Link>
                                </div>
                            )}
                        </nav>
                    )}
                </div>
            </header>

            {/* ══ Main Content ═════════════════════════════════════════════ */}
            <main className="flex-1">
                {children}
            </main>

            {/* ══ IEC Footer ═══════════════════════════════════════════════ */}
            <footer className="iec-footer">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10 pb-10
                                    border-b border-white/10">

                        {/* Col 1 – Brand */}
                        <div className="sm:col-span-2 lg:col-span-1">
                            <div className="flex items-center gap-3 mb-4">
                                <img src="/asset/logo.png" alt="IEC Logo"
                                     className="w-14 h-14 object-contain" />
                                <div>
                                    <p className="text-sm font-bold text-white leading-snug">
                                        Independent Electoral Commission
                                    </p>
                                    <p className="text-xs text-gray-400 mt-0.5">The Gambia</p>
                                </div>
                            </div>
                            <p className="text-sm text-gray-400 leading-relaxed mb-5 max-w-xs">
                                Fair-Play, Integrity and Transparency in all electoral processes of The Gambia.
                            </p>
                            {/* Social icons */}
                            <div className="flex gap-2 flex-wrap">
                                {SOCIALS.map(s => (
                                    <a key={s.name} href={s.href} aria-label={s.label}
                                       target="_blank" rel="noopener noreferrer"
                                       className="w-8 h-8 rounded-full bg-white/10 hover:bg-iec-pink-500 flex items-center justify-center text-white transition-colors">
                                        <SocialIcon name={s.name} />
                                    </a>
                                ))}
                            </div>
                        </div>

                        {/* Col 2 – Contact */}
                        <div>
                            <h3 className="text-xs font-bold uppercase tracking-widest text-gray-400 mb-4">
                                Contact
                            </h3>
                            <ul className="space-y-3">
                                <li className="flex items-center gap-2.5 text-sm text-gray-400">
                                    <svg className="w-4 h-4 shrink-0 text-iec-pink-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2.003 5.884L10 9.882l7.997-3.998A2 2 0 0016 4H4a2 2 0 00-1.997 1.884z"/>
                                        <path d="M18 8.118l-8 4-8-4V14a2 2 0 002 2h12a2 2 0 002-2V8.118z"/>
                                    </svg>
                                    <a href="mailto:admin@iec.gm" className="hover:text-white transition-colors">
                                        admin@iec.gm
                                    </a>
                                </li>
                                <li className="flex items-center gap-2.5 text-sm text-gray-400">
                                    <svg className="w-4 h-4 shrink-0 text-iec-pink-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"/>
                                    </svg>
                                    (+220) 4373804
                                </li>
                                <li className="flex items-start gap-2.5 text-sm text-gray-400">
                                    <svg className="w-4 h-4 shrink-0 mt-0.5 text-iec-pink-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fillRule="evenodd" d="M5.05 4.05a7 7 0 119.9 9.9L10 18.9l-4.95-4.95a7 7 0 010-9.9zM10 11a2 2 0 100-4 2 2 0 000 4z" clipRule="evenodd"/>
                                    </svg>
                                    <span>Election House, Bertil Harding Highway, Kanifing East Layout,<br/>P.O. Box 793 Banjul, The Gambia</span>
                                </li>
                            </ul>
                        </div>

                        {/* Col 3 – Platform Links */}
                        <div>
                            <h3 className="text-xs font-bold uppercase tracking-widest text-gray-400 mb-4">
                                Platform
                            </h3>
                            <ul className="space-y-2.5">
                                {FOOTER_LINKS.map(link => (
                                    <li key={link.href}>
                                        <Link href={link.href}
                                            className="text-sm text-gray-400 hover:text-white hover:pl-1.5 transition-all">
                                            {link.label}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </div>

                        {/* Col 4 – Security */}
                        <div>
                            <h3 className="text-xs font-bold uppercase tracking-widest text-gray-400 mb-4">
                                Security &amp; Integrity
                            </h3>
                            <ul className="space-y-3">
                                {SECURITY_ITEMS.map((item, i) => (
                                    <li key={i} className="flex items-center gap-2.5 text-sm text-gray-400">
                                        <span className="text-base leading-none">{item.icon}</span>
                                        {item.text}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    </div>

                    {/* Copyright bar */}
                    <div className="pt-6 flex flex-col sm:flex-row items-center justify-between gap-3">
                        <p className="text-sm text-gray-500">
                            © {new Date().getFullYear()} Independent Electoral Commission – IEC – The Gambia.
                            All rights reserved.
                        </p>
                        <p className="text-xs text-gray-600">
                            National Elections Results &amp; Transparency Platform (NERTP)
                        </p>
                    </div>
                </div>
            </footer>

        </div>
    );
}