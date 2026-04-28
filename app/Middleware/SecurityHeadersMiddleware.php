<?php

namespace App\Middleware;

/**
 * Middleware de headers de segurança HTTP.
 */
class SecurityHeadersMiddleware
{
    public function handle(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');

        // CSP: CDNs necessários para Tailwind (tailwindcss.com), Alpine.js, Chart.js e
        // FullCalendar (jsdelivr.net). 'unsafe-eval' exigido pelo Tailwind CDN para geração
        // dinâmica de estilos. Migrar para build local + nonces em sprint futuro.
        $cdnScript = "https://cdn.tailwindcss.com https://cdn.jsdelivr.net";
        $cdnStyle  = "https://cdn.tailwindcss.com https://cdn.jsdelivr.net";

        $csp = implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' {$cdnScript}",
            "style-src 'self' 'unsafe-inline' {$cdnStyle}",
            "img-src 'self' data: blob:",
            "font-src 'self' https://cdn.jsdelivr.net",
            "connect-src 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ]);
        header("Content-Security-Policy: {$csp}");

        if (config('app.env') === 'production') {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }
}
