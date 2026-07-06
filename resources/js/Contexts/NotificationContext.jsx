import { createContext, useContext } from 'react';
import { useNotifications, ToastContainer } from '@/Components/Notifications';
import { AppError } from '@/Utils/apiClient';

/**
 * NotificationContext — application-wide notification bus.
 *
 * Mounted once at the root (see app.jsx) so ANY component, anywhere in the
 * tree, can call useGlobalNotify() and be guaranteed the toast renders,
 * even if the component that triggered the action unmounts right after
 * (e.g. a modal that closes on submit).
 */
const NotificationContext = createContext(null);

export function NotificationProvider({ children }) {
    const { toasts, removeNotification, notify } = useNotifications();

    // Normalizes any thrown value (AppError, generic Error, string) into a
    // single error toast. This is the "never fail silently" backstop —
    // call this in every catch block instead of swallowing the error.
    const notifyError = (err, fallback = 'Something went wrong. Please try again.') => {
        if (err instanceof AppError) {
            notify.error(err.message || fallback);
            return err;
        }
        if (err instanceof Error) {
            notify.error(err.message || fallback);
            return err;
        }
        if (typeof err === 'string') {
            notify.error(err);
            return err;
        }
        notify.error(fallback);
        return err;
    };

    return (
        <NotificationContext.Provider value={{ notify, notifyError }}>
            {children}
            <ToastContainer toasts={toasts} onRemoveToast={removeNotification} />
        </NotificationContext.Provider>
    );
}

export function useGlobalNotify() {
    const ctx = useContext(NotificationContext);
    if (!ctx) {
        throw new Error('useGlobalNotify() must be used within <NotificationProvider>');
    }
    return ctx;
}