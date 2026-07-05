/**
 * Adapters de integraciones para el componente compartido `IntegrationsManager`.
 *
 * El catálogo de proveedores/servicios es GLOBAL (consts de TenantApiConfig); lo
 * único que cambia entre "configurar mi tenant" y "configurar otro tenant
 * (super admin)" es el CRUD. Cada adapter expone la MISMA interfaz sobre un
 * endpoint distinto, para que la UI viva en un solo lugar.
 */
import { v2 } from '@/services/v2'
import type {
  V2Integration,
  V2IntegrationPayload,
  V2IntegrationTestPayload,
  V2ProviderOption,
} from '@/modules/admin/services/integration.staff.service'

export interface IntegrationsAdapter {
  getOptions(): Promise<{
    providers: V2ProviderOption[]
    service_types: Record<string, string>
    service_type_descriptions: Record<string, string>
  }>
  list(): Promise<V2Integration[]>
  save(payload: V2IntegrationPayload): Promise<void>
  test(id: string, payload: V2IntegrationTestPayload): Promise<{ success: boolean; message: string }>
  toggle(integration: V2Integration): Promise<void>
  destroy(id: string): Promise<void>
}

/** Integraciones del tenant actual (auto-configuración, /staff/integrations). */
export const selfIntegrationsAdapter: IntegrationsAdapter = {
  async getOptions() {
    const res = await v2.staff.integration.getOptions()
    return {
      providers: res.data?.providers ?? [],
      service_types: res.data?.service_types ?? {},
      service_type_descriptions: res.data?.service_type_descriptions ?? {},
    }
  },
  async list() {
    const res = await v2.staff.integration.list()
    return res.data?.integrations ?? []
  },
  async save(payload) {
    await v2.staff.integration.save(payload)
  },
  async test(id, payload) {
    const res = await v2.staff.integration.test(id, payload)
    return { success: !!res.success, message: res.message ?? '' }
  },
  async toggle(integration) {
    await v2.staff.integration.toggle(integration.id)
  },
  async destroy(id) {
    await v2.staff.integration.destroy(id)
  },
}

/**
 * Integraciones de un tenant específico (super admin, /staff/tenants/{id}/api-configs).
 * El catálogo (providers/service_types) y los api_configs salen del mismo
 * `getConfig`; el resto reusa los endpoints per-tenant.
 */
export function tenantIntegrationsAdapter(tenantId: string): IntegrationsAdapter {
  return {
    async getOptions() {
      const res = await v2.staff.tenant.getConfig(tenantId)
      return {
        providers: res.data?.providers ?? [],
        service_types: res.data?.service_types ?? {},
        service_type_descriptions: res.data?.service_type_descriptions ?? {},
      }
    },
    async list() {
      const res = await v2.staff.tenant.getConfig(tenantId)
      // api_configs viene de toApiArray() — mismo shape que V2Integration.
      return (res.data?.api_configs ?? []) as unknown as V2Integration[]
    },
    async save(payload) {
      await v2.staff.tenant.saveApiConfig(tenantId, payload)
    },
    async test(id, payload) {
      const res = await v2.staff.tenant.testApiConfig(tenantId, id, payload)
      return { success: !!res.success, message: res.message ?? '' }
    },
    async toggle(integration) {
      // El endpoint per-tenant no tiene "toggle"; reusamos save con is_active invertido.
      await v2.staff.tenant.saveApiConfig(tenantId, {
        provider: integration.provider,
        service_type: integration.service_type,
        is_active: !integration.is_active,
      })
    },
    async destroy(id) {
      await v2.staff.tenant.deleteApiConfig(tenantId, id)
    },
  }
}
