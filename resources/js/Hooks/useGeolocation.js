import { useState, useEffect } from 'react';

/**
 * useGeolocation Hook - Tracks officer's GPS location.
 * 
 * From architecture: resources/js/Hooks/useGeolocation.js
 * 
 * Features:
 * - Continuous location tracking with high accuracy
 * - Error handling for permission denied
 * - Returns lat/lng/accuracy for GPS middleware headers
 */
export function useGeolocation(options = {}) {
    const [location, setLocation] = useState(null);
    const [error, setError] = useState(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        if (!navigator.geolocation) {
            setError('Geolocation is not supported by your device');
            setLoading(false);
            return;
        }

        const watchOptions = {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0,
            ...options,
        };

        const handleSuccess = (position) => {
            setLocation({
                latitude: position.coords.latitude,
                longitude: position.coords.longitude,
                accuracy: position.coords.accuracy,
                timestamp: position.timestamp,
            });
            setError(null);
            setLoading(false);
        };

        const handleError = (err) => {
            let message = 'Unable to retrieve your location';
            
            switch (err.code) {
                case err.PERMISSION_DENIED:
                    message = 'Location permission denied. Please enable GPS in your device settings.';
                    break;
                case err.POSITION_UNAVAILABLE:
                    message = 'Location information is unavailable. Please move to an area with better GPS signal.';
                    break;
                case err.TIMEOUT:
                    message = 'Location request timed out. Please try again.';
                    break;
            }

            setError(message);
            setLoading(false);
        };

        // Watch position continuously
        const watchId = navigator.geolocation.watchPosition(
            handleSuccess,
            handleError,
            watchOptions
        );

        // Cleanup
        return () => {
            navigator.geolocation.clearWatch(watchId);
        };
    }, []);

    return { location, error, loading };
}
