<?php

namespace App\Http\Middleware;

use App\Services\MetadataService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureMetadata
{
    protected MetadataService $metadataService;

    public function __construct(MetadataService $metadataService)
    {
        $this->metadataService = $metadataService;
    }

    /**
     * Handle an incoming request.
     *
     * Captures device and geolocation metadata and makes it available
     * to controllers via $request->attributes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Capture metadata (estructura anidada) y aplánarlo para que los
        // consumidores (controllers que insertan en audit_logs) tengan las
        // claves al mismo nivel.
        $captured = $this->metadataService->capture($request);
        $metadata = $this->metadataService->flatten($captured);

        // Store in request attributes for controller access
        $request->attributes->set('metadata', $metadata);

        // Store individual pieces for convenience
        $request->attributes->set('client_ip', $metadata['ip_address']);
        $request->attributes->set('device_type', $metadata['device_type']);

        return $next($request);
    }
}
