<template>
  <!-- Integraciones del tenant actual. La UI vive en IntegrationsManager
       (compartida con AdminTenants vía adapters). -->
  <IntegrationsManager :adapter="selfIntegrationsAdapter" />
</template>

<script setup lang="ts">
import IntegrationsManager from '@/modules/admin/components/IntegrationsManager.vue'
import { selfIntegrationsAdapter } from '@/modules/admin/services/integrationsAdapters'
</script>
