import { Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import { useEffect, useState } from 'react';

export default function Welcome() {
    const [isVisible, setIsVisible] = useState({});

    useEffect(() => {
        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        setIsVisible(prev => ({ ...prev, [entry.target.id]: true }));
                    }
                });
            },
            { threshold: 0.1 }
        );

        document.querySelectorAll('[data-animate]').forEach((el) => {
            observer.observe(el);
        });

        return () => observer.disconnect();
    }, []);

    return (
        <AppLayout>
            <div className="container mx-auto px-4 py-12 sm:py-20">
                <div className="max-w-6xl mx-auto">
                    {/* Hero Section */}
                    <div className="text-center mb-16 animate-fade-in-up">
                        <h2 className="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold text-white mb-6 leading-tight">
                            National Elections
                            <br />
                            <span className="text-transparent bg-clip-text bg-gradient-to-r from-pink-400 to-pink-600 animate-gradient">
                                Results & Transparency
                            </span>
                        </h2>
                        <p className="text-lg sm:text-xl text-gray-300 mb-8 px-4">
                            A secure, transparent, and real-time platform for election results management in The Gambia.
                        </p>
                    </div>

                    {/* Feature Cards */}
                    <div
                        id="features"
                        data-animate
                        className={`grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 sm:gap-8 mb-12 px-4 transition-all duration-1000 ${
                            isVisible.features ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'
                        }`}
                    >
                        {/* Secure Card */}
                        <div className="group bg-white/10 backdrop-blur-sm rounded-xl p-6 sm:p-8 border border-white/20 hover:border-pink-500/50 hover:bg-white/15 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-pink-500/20">
                            <div className="flex justify-center items-center mb-6">
                                <div className="w-20 h-20 flex items-center justify-center bg-pink-400/20 rounded-full group-hover:bg-pink-500/30 transition-all duration-300 group-hover:scale-110">
                                    <img
                                        src="/asset/secure.png"
                                        alt="Secure"
                                        className="w-20 h-20 flex items-center justify-center rounded-full transition-all duration-300 group-hover:scale-110"
                                    />
                                </div>
                            </div>
                            <h3 className="text-xl sm:text-2xl font-bold text-white mb-3 text-center group-hover:text-pink-400 transition-colors">
                                Secure
                            </h3>
                            <p className="text-gray-300 text-center text-sm sm:text-base">
                                Multi-factor authentication and device binding for maximum security
                            </p>
                        </div>

                        {/* Transparent Card */}
                        <div className="group bg-white/10 backdrop-blur-sm rounded-xl p-6 sm:p-8 border border-white/20 hover:border-pink-500/50 hover:bg-white/15 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-pink-500/20">
                            <div className="flex justify-center items-center mb-6">
                                <div className="w-20 h-20 flex items-center justify-center bg-pink-400/20 rounded-full group-hover:bg-pink-500/30 transition-all duration-300 group-hover:scale-110">
                                    <img
                                        src="/asset/transparent.png"
                                        alt="Transparent"
                                        className="w-20 h-20 flex items-center justify-center rounded-full transition-all duration-300 group-hover:scale-110"
                                    />
                                </div>
                            </div>
                            <h3 className="text-xl sm:text-2xl font-bold text-white mb-3 text-center group-hover:text-pink-400 transition-colors">
                                Transparent
                            </h3>
                            <p className="text-gray-300 text-center text-sm sm:text-base">
                                Public access to certified election results with full audit trail
                            </p>
                        </div>

                        {/* Real-time Card */}
                        <div className="group bg-white/10 backdrop-blur-sm rounded-xl p-6 sm:p-8 border border-white/20 hover:border-pink-500/50 hover:bg-white/15 transition-all duration-300 hover:scale-105 hover:shadow-2xl hover:shadow-pink-500/20 sm:col-span-2 lg:col-span-1">
                            <div className="flex justify-center items-center mb-6">
                                <div className="w-20 h-20 flex items-center justify-center bg-pink-400/20 rounded-full group-hover:bg-pink-500/30 transition-all duration-300 group-hover:scale-110">
                                    <img
                                        src="/asset/real-time.png"
                                        alt="Real-time"
                                        className="w-20 h-20 flex items-center justify-center rounded-full transition-all duration-300 group-hover:scale-110"
                                    />
                                </div>
                            </div>
                            <h3 className="text-xl sm:text-2xl font-bold text-white mb-3 text-center group-hover:text-pink-400 transition-colors">
                                Real-time
                            </h3>
                            <p className="text-gray-300 text-center text-sm sm:text-base">
                                Live results as they are certified through sequential approval
                            </p>
                        </div>
                    </div>

                    {/* CTA Buttons */}
                    <div
                        id="cta"
                        data-animate
                        className={`flex flex-col sm:flex-row gap-4 sm:gap-6 justify-center items-stretch sm:items-center px-4 transition-all duration-1000 delay-300 ${
                            isVisible.cta ? 'opacity-100 translate-y-0' : 'opacity-0 translate-y-10'
                        }`}
                    >
                        <a
                            href="/auth/login"
                            className="w-full sm:w-auto px-8 sm:px-10 py-4 bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white rounded-xl font-bold text-base sm:text-lg shadow-xl transform hover:scale-105 transition-all text-center"
                        >
                            IEC Staff Login
                        </a>
                        <a
                            href="/results"
                            className="w-full sm:w-auto px-8 sm:px-10 py-4 bg-gradient-to-r from-pink-500 to-pink-600 hover:from-pink-600 hover:to-pink-700 text-white rounded-xl font-bold text-base sm:text-lg shadow-xl transform hover:scale-105 transition-all text-center"
                        >
                            View Public Results
                        </a>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
