<template>
  <!-- Integraciones del tenant actual. La UI vive en IntegrationsManager
       (compartida con AdminTenants vía adapters). -->
  <IntegrationsManager :adapter="selfIntegrationsAdapter" />
</template>

<script setup lang="ts">
import IntegrationsManager from '@/components/admin/IntegrationsManager.vue'
import { selfIntegrationsAdapter } from '@/services/v2/integrationsAdapters'
</script>
