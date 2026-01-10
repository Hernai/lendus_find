/**
 * Hybrid Tenant Detection Utility
 *
 * Detects tenant from (in order of priority):
 * 1. Subdomain (e.g., demo.lendus.com) - for production
 * 2. Path prefix (e.g., /demo/...) - for development with ngrok
 * 3. Environment variable VITE_TENANT_ID - fallback
 */

// Reserved subdomains that are NOT tenant slugs
const RESERVED_SUBDOMAINS = ['www', 'app', 'api', 'admin', 'localhost']

// Domains where the subdomain is NOT a tenant slug (e.g., ngrok random IDs)
const EXCLUDED_PARENT_DOMAINS = ['ngrok-free.app', 'ngrok.io', 'localhost']

// Reserved paths that are NOT tenant slugs
const RESERVED_PATHS = ['auth', 'admin', 'solicitud', 'dashboard', 'simulador', 'perfil', 'correcciones']

/**
 * Get subdomain from current hostname
 * e.g., demo.lendus.com -> 'demo'
 */
function getSubdomain(): string | null {
  const hostname = window.location.hostname
  const parts = hostname.split('.')

  // Check if this is an excluded domain (like ngrok) where subdomain is random
  if (EXCLUDED_PARENT_DOMAINS.some(d => hostname.endsWith(d))) {
    return null
  }

  // Need at least 3 parts for subdomain (sub.domain.tld)
  // Or 2 parts for localhost (sub.localhost)
  const firstPart = parts[0]
  if (parts.length >= 2 && firstPart) {
    const subdomain = firstPart.toLowerCase()
    // Check if it's a real subdomain and not reserved
    if (!RESERVED_SUBDOMAINS.includes(subdomain) && subdomain !== hostname) {
      return subdomain
    }
  }

  return null
}

/**
 * Get tenant from URL path prefix
 * e.g., /demo/simulador -> 'demo'
 */
function getPathTenant(): string | null {
  const path = window.location.pathname
  const segments = path.split('/').filter(Boolean)

  const firstSegment = segments[0]
  if (segments.length > 0 && firstSegment) {
    const segment = firstSegment.toLowerCase()
    // Check if it's not a reserved path
    if (!RESERVED_PATHS.includes(segment)) {
      return segment
    }
  }

  return null
}

/**
 * Detect tenant slug from URL or environment (hybrid approach)
 */
export function detectTenantSlug(): string {
  // 1. Try subdomain first (production)
  const subdomain = getSubdomain()
  if (subdomain) {
    return subdomain
  }

  // 2. Try path prefix (development with ngrok)
  const pathTenant = getPathTenant()
  if (pathTenant) {
    return pathTenant
  }

  // 3. Fall back to environment variable
  return import.meta.env.VITE_TENANT_ID || 'demo'
}

/**
 * Get the base path for the current tenant
 * Returns empty string if no tenant prefix in URL
 */
export function getTenantBasePath(): string {
  const pathTenant = getPathTenant()
  return pathTenant ? `/${pathTenant}` : ''
}
