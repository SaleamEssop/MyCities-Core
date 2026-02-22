/**
 * Monitoring Listener
 * 
 * Captures browser-level errors and sends them to monitoring API.
 * Boundary-level observation only - no application code intrusion.
 */

(function() {
    'use strict';

    let currentSessionId = null;
    let isActive = false;
    let errorHandlers = [];

    /**
     * Start monitoring - activate all error listeners
     */
    function start(sessionId) {
        if (isActive) {
            stop();
        }

        currentSessionId = sessionId;
        isActive = true;

        // Global error handler
        const globalErrorHandler = function(message, source, lineno, colno, error) {
            if (!isActive || !currentSessionId) return;

            sendEvent({
                source: 'browser',
                type: 'js_error',
                severity: 'error',
                data: {
                    message: message,
                    source: source,
                    line: lineno,
                    column: colno,
                    stack: error ? error.stack : null,
                    url: window.location.href
                }
            });
        };
        window.onerror = globalErrorHandler;
        errorHandlers.push({ type: 'onerror', handler: globalErrorHandler });

        // Unhandled promise rejection
        const unhandledRejectionHandler = function(event) {
            if (!isActive || !currentSessionId) return;

            sendEvent({
                source: 'browser',
                type: 'promise_rejection',
                severity: 'error',
                data: {
                    reason: event.reason ? String(event.reason) : 'Unknown',
                    url: window.location.href
                }
            });
        };
        window.addEventListener('unhandledrejection', unhandledRejectionHandler);
        errorHandlers.push({ type: 'unhandledrejection', handler: unhandledRejectionHandler });

        // Console error interception (if possible)
        const originalConsoleError = console.error;
        console.error = function(...args) {
            if (isActive && currentSessionId) {
                sendEvent({
                    source: 'browser',
                    type: 'console_error',
                    severity: 'warning',
                    data: {
                        message: args.map(arg => String(arg)).join(' '),
                        url: window.location.href
                    }
                });
            }
            originalConsoleError.apply(console, args);
        };
        errorHandlers.push({ type: 'console.error', handler: originalConsoleError });

        // Iframe error handling (for embedded webapp)
        const iframe = document.getElementById('webapp-iframe');
        if (iframe) {
            iframe.addEventListener('load', function() {
                try {
                    // Try to access iframe content (same-origin only)
                    const iframeWindow = iframe.contentWindow;
                    if (iframeWindow) {
                        const iframeErrorHandler = function(message, source, lineno, colno, error) {
                            if (!isActive || !currentSessionId) return;

                            sendEvent({
                                source: 'browser',
                                type: 'iframe_error',
                                severity: 'error',
                                data: {
                                    message: message,
                                    source: source,
                                    line: lineno,
                                    column: colno,
                                    stack: error ? error.stack : null,
                                    iframe_url: iframe.src
                                }
                            });
                        };
                        iframeWindow.onerror = iframeErrorHandler;
                        errorHandlers.push({ type: 'iframe.onerror', handler: iframeErrorHandler });
                    }
                } catch (e) {
                    // Cross-origin iframe - cannot access
                    // This is expected and safe to ignore
                }
            });
        }

        console.log('[Monitoring] Started - Session ID:', sessionId);
    }

    /**
     * Stop monitoring - deactivate all error listeners
     */
    function stop() {
        if (!isActive) return;

        isActive = false;

        // Restore original handlers
        errorHandlers.forEach(({ type, handler }) => {
            if (type === 'onerror') {
                window.onerror = null;
            } else if (type === 'unhandledrejection') {
                window.removeEventListener('unhandledrejection', handler);
            } else if (type === 'console.error') {
                console.error = handler;
            }
        });

        errorHandlers = [];
        currentSessionId = null;

        console.log('[Monitoring] Stopped');
    }

    /**
     * Send event to monitoring API
     */
    async function sendEvent(event) {
        if (!currentSessionId || !isActive) return;

        try {
            const response = await fetch(`/admin/monitoring/events/${currentSessionId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify(event)
            });

            if (!response.ok) {
                console.warn('[Monitoring] Failed to send event:', response.statusText);
            }
        } catch (error) {
            // Fail-safe: Don't block application if monitoring fails
            console.warn('[Monitoring] Error sending event:', error);
        }
    }

    // Expose API
    window.monitoringListener = {
        start: start,
        stop: stop,
        isActive: function() { return isActive; },
        getSessionId: function() { return currentSessionId; }
    };
})();





















