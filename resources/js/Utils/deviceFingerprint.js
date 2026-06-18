/**
 * Device Fingerprinting Utility
 *
 * Generates a comprehensive device fingerprint using multiple characteristics:
 * - Operating System (from User-Agent)
 * - Platform
 * - Device Type
 * - Screen Resolution
 * - Time Zone
 * - Language
 * - Touch Capability
 * - Canvas Fingerprint Hash
 * - WebGL Fingerprint Hash
 *
 * This fingerprint is used for device binding, not for tracking.
 * Browser name/version are intentionally excluded to allow browser updates
 * and switching on the same physical device.
 */

/**
 * Get the device's operating system from User-Agent
 */
function getOS() {
    const ua = navigator.userAgent;

    if (ua.includes('Windows')) return 'Windows';
    if (ua.includes('Mac')) return 'macOS';
    if (ua.includes('Linux')) return 'Linux';
    if (ua.includes('Android')) return 'Android';
    if (ua.includes('iPhone') || ua.includes('iPad')) return 'iOS';
    if (ua.includes('ChromeOS')) return 'ChromeOS';

    return 'Unknown';
}

/**
 * Get the device's platform
 */
function getPlatform() {
    return navigator.platform || 'Unknown';
}

/**
 * Detect device type from User-Agent
 */
function getDeviceType() {
    const ua = navigator.userAgent.toLowerCase();

    if (ua.includes('tablet') || ua.includes('ipad')) return 'tablet';
    if (ua.includes('mobile') || ua.includes('android')) return 'mobile';

    return 'desktop';
}

/**
 * Get CPU core count (if available)
 */
function getCpuCores() {
    return navigator.hardwareConcurrency || 0;
}

/**
 * Get device memory (if available)
 */
function getDeviceMemory() {
    return navigator.deviceMemory || 0;
}

/**
 * Get screen resolution and related info
 */
function getScreenInfo() {
    return {
        width: window.screen.width,
        height: window.screen.height,
        colorDepth: window.screen.colorDepth,
        pixelDepth: window.screen.pixelDepth,
        devicePixelRatio: window.devicePixelRatio,
    };
}

/**
 * Get timezone information
 */
function getTimezoneInfo() {
    const date = new Date();
    const offset = -date.getTimezoneOffset();
    const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

    return {
        offset,
        timezone,
    };
}

/**
 * Get language preferences
 */
function getLanguageInfo() {
    return {
        lang: navigator.language || navigator.userLanguage,
        languages: navigator.languages ? navigator.languages.slice(0, 3) : [],
    };
}

/**
 * Check touch capability
 */
function getTouchCapability() {
    return {
        touchEnabled: () => {
            return (
                ('ontouchstart' in window) ||
                (navigator.maxTouchPoints > 0) ||
                (navigator.msMaxTouchPoints > 0)
            );
        },
        maxTouchPoints: navigator.maxTouchPoints || 0,
    };
}

/**
 * Generate Canvas fingerprint hash
 * Returns a hash based on rendering characteristics
 */
function getCanvasFingerprint() {
    try {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        if (!ctx) return null;

        canvas.width = 280;
        canvas.height = 60;

        // Set background
        ctx.fillStyle = '#f60';
        ctx.fillRect(125, 1, 150, 62);

        // Set text properties
        ctx.fillStyle = '#069';
        ctx.font = "italic 40px 'Courier New'";
        ctx.fillText('IEC Device Binding', 2, 15);

        // Add some geometric shapes to test rendering
        ctx.strokeStyle = '#069';
        ctx.beginPath();
        ctx.arc(50, 30, 20, 0, Math.PI * 2);
        ctx.stroke();

        // Get the canvas data
        const dataUrl = canvas.toDataURL();

        // Simple hash function
        return simpleHash(dataUrl);
    } catch (e) {
        return null;
    }
}

/**
 * Generate WebGL fingerprint hash
 * Returns a hash based on WebGL capabilities
 */
function getWebglFingerprint() {
    try {
        const canvas = document.createElement('canvas');
        const gl =
            canvas.getContext('webgl') ||
            canvas.getContext('experimental-webgl');

        if (!gl) return null;

        const debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
        let info = '';

        if (debugInfo) {
            info = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL);
        }

        // Add some WebGL capabilities
        info += '|' + gl.getParameter(gl.MAX_TEXTURE_SIZE);
        info += '|' + gl.getParameter(gl.MAX_VIEWPORT_DIMS);

        return simpleHash(info);
    } catch (e) {
        return null;
    }
}

/**
 * Simple hash function for fingerprint components
 * Not meant for security, just for generating consistent hashes
 */
function simpleHash(str) {
    let hash = 0;
    if (str.length === 0) return hash.toString();

    for (let i = 0; i < str.length; i++) {
        const char = str.charCodeAt(i);
        hash = (hash << 5) - hash + char;
        hash = hash & hash; // Convert to 32bit integer
    }

    return Math.abs(hash).toString(16);
}

/**
 * Generate the complete device fingerprint object
 * Contains all device characteristics
 */
export function generateDeviceFingerprint() {
    const touchCap = getTouchCapability();

    return {
        os: getOS(),
        platform: getPlatform(),
        deviceType: getDeviceType(),
        cpuCores: getCpuCores(),
        deviceMemory: getDeviceMemory(),
        screen: getScreenInfo(),
        timezone: getTimezoneInfo(),
        language: getLanguageInfo(),
        touchEnabled: touchCap.touchEnabled(),
        maxTouchPoints: touchCap.maxTouchPoints,
        canvasFingerprint: getCanvasFingerprint(),
        webglFingerprint: getWebglFingerprint(),
    };
}

/**
 * Generate a compact hash of the fingerprint for transmission
 * This is for efficient storage and comparison
 */
export function generateFingerprintHash(fingerprint) {
    const json = JSON.stringify(fingerprint);
    return simpleHash(json);
}

/**
 * Serialize the fingerprint for sending to the server
 */
export function serializeFingerprint(fingerprint) {
    return JSON.stringify(fingerprint);
}

/**
 * Parse fingerprint from JSON string
 */
export function parseFingerprint(json) {
    try {
        return JSON.parse(json);
    } catch (e) {
        return null;
    }
}
