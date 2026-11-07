/**
 * Base64URL Conversion utilities for WebAuthn
 */
const rsBase64url = {
    encode: function(buffer) {
        const base64 = window.btoa(String.fromCharCode(...new Uint8Array(buffer)));
        return base64.replace(/=/g, '').replace(/\+/g, '-').replace(/\//g, '_');
    },

    decode: function(base64url) {
        const padding = '='.repeat((4 - base64url.length % 4) % 4);
        const base64 = (base64url + padding).replace(/-/g, '+').replace(/_/g, '/');
        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);
        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }
        return outputArray.buffer;
    }
};
