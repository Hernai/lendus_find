<script setup lang="ts">
// Admin layout placeholder
</script>

<template>
  <div class="min-h-screen bg-gray-100">
    <nav class="bg-gray-900 text-white px-4 py-3">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        <span class="font-bold">Panel Admin</span>
        <div class="flex gap-4">
          <router-link to="/admin" class="hover:text-gray-300">Dashboard</router-link>
          <router-link to="/admin/solicitudes" class="hover:text-gray-300">Solicitudes</router-link>
        </div>
      </div>
    </nav>
    <main class="max-w-7xl mx-auto p-4">
      <router-view />
    </main>
  </div>
</template>
