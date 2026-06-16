import { useState, useCallback, useEffect } from 'react';

/**
 * Toast Notification Component
 * Displays non-intrusive notifications at the bottom-right of the page
 */
export function Toast({ message, type = 'info', duration = 5000, onClose }) {
    useEffect(() => {
        if (duration) {
            const timer = setTimeout(onClose, duration);
            return () => clearTimeout(timer);
        }
    }, [duration, onClose]);

    const colorClasses = {
        success: 'bg-green-600 text-white',
        error: 'bg-red-600 text-white',
        warning: 'bg-amber-600 text-white',
        info: 'bg-blue-600 text-white',
    };

    const icons = {
        success: '✓',
        error: '✕',
        warning: '⚠',
        info: 'ℹ',
    };

    return (
        <div
            className={`flex items-center gap-3 px-4 py-3 rounded-lg shadow-lg animate-slide-in ${colorClasses[type]}`}
            role="alert"
        >
            <span className="text-lg font-bold">{icons[type]}</span>
            <span className="flex-1">{message}</span>
            <button
                onClick={onClose}
                className="ml-2 text-lg hover:opacity-70 transition-opacity"
                aria-label="Close"
            >
                ✕
            </button>
        </div>
    );
}

/**
 * Toast Container Component
 * Manages multiple toasts and their lifecycle
 */
export function ToastContainer({ toasts, onRemoveToast }) {
    return (
        <div className="fixed bottom-4 right-4 z-50 space-y-2 pointer-events-none">
            {toasts.map((toast) => (
                <div key={toast.id} className="pointer-events-auto">
                    <Toast
                        message={toast.message}
                        type={toast.type}
                        duration={toast.duration}
                        onClose={() => onRemoveToast(toast.id)}
                    />
                </div>
            ))}
        </div>
    );
}

/**
 * useNotifications Hook
 * Provides toast notification management
 * 
 * Usage:
 * const { addNotification } = useNotifications();
 * addNotification('Success!', 'success');
 */
export function useNotifications() {
    const [toasts, setToasts] = useState([]);

    const addNotification = useCallback((message, type = 'info', duration = 5000) => {
        const id = Date.now() + Math.random();
        const toast = { id, message, type, duration };

        setToasts((prev) => [...prev, toast]);
        return id;
    }, []);

    const removeNotification = useCallback((id) => {
        setToasts((prev) => prev.filter((toast) => toast.id !== id));
    }, []);

    const notify = {
        success: (message, duration) => addNotification(message, 'success', duration),
        error: (message, duration) => addNotification(message, 'error', duration),
        warning: (message, duration) => addNotification(message, 'warning', duration),
        info: (message, duration) => addNotification(message, 'info', duration),
    };

    return { toasts, addNotification, removeNotification, notify };
}
