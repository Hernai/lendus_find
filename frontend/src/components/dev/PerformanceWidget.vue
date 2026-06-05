<script setup lang="ts">
import { computed, ref } from 'vue'
import { perf, perfStats } from '@/utils/perf'

const isEnabled = computed(() => perf.state.enabled)
const isVisible = computed(() => perf.state.visible)
const metrics = computed(() => perf.state.metrics)
const stats = perfStats

const filter = ref<'all' | 'api' | 'mount' | 'nav'>('all')
const minimized = ref(false)

const filtered = computed(() => {
  if (filter.value === 'all') return metrics.value
  return metrics.value.filter((m) => m.type === filter.value)
})

function colorForDuration(ms: number): string {
  if (ms < 100) return 'text-green-400'
  if (ms < 500) return 'text-yellow-300'
  if (ms < 1500) return 'text-orange-400'
  return 'text-red-400'
}

function methodColor(method: string): string {
  switch (method.toUpperCase()) {
    case 'GET':
      return 'text-sky-300'
    case 'POST':
      return 'text-emerald-300'
    case 'PUT':
    case 'PATCH':
      return 'text-amber-300'
    case 'DELETE':
      return 'text-rose-300'
    default:
      return 'text-gray-300'
  }
}

function shortUrl(url: string): string {
  try {
    const u = new URL(url, window.location.origin)
    return u.pathname.replace(/^\/api\/v2/, '') + (u.search || '')
  } catch {
    return url
  }
}
</script>

<template>
  <div
    v-if="isEnabled && isVisible"
    class="perf-widget"
    :class="{ 'is-minimized': minimized }"
  >
    <header class="perf-header">
      <div class="perf-title">
        <span class="perf-dot"></span>
        <strong>Perf</strong>
        <span class="perf-stats" v-if="!minimized">
          {{ stats.count }} reqs · avg {{ stats.avg }}ms · p95 {{ stats.p95 }}ms
          <span v-if="stats.errors > 0" class="perf-errors">· {{ stats.errors }} err</span>
        </span>
      </div>
      <div class="perf-actions">
        <button @click="minimized = !minimized" class="perf-btn" :title="minimized ? 'Expandir' : 'Minimizar'">
          {{ minimized ? '▢' : '_' }}
        </button>
        <button @click="perf.clear()" class="perf-btn" title="Limpiar">⌫</button>
        <button @click="perf.toggleVisible()" class="perf-btn" title="Ocultar (Ctrl+Shift+P para volver)">×</button>
      </div>
    </header>

    <div v-if="!minimized" class="perf-body">
      <div class="perf-filters">
        <button
          v-for="f in ['all', 'api', 'mount', 'nav'] as const"
          :key="f"
          @click="filter = f"
          class="perf-filter"
          :class="{ active: filter === f }"
        >
          {{ f }}
        </button>
      </div>

      <div class="perf-list">
        <div
          v-for="m in filtered"
          :key="m.id"
          class="perf-row"
          :class="`perf-row-${m.type}`"
        >
          <template v-if="m.type === 'api'">
            <span class="perf-method" :class="methodColor(m.method)">{{ m.method }}</span>
            <span class="perf-url" :title="m.url">{{ shortUrl(m.url) }}</span>
            <span v-if="m.status" class="perf-status" :class="{ 'perf-err': m.status >= 400 }">{{ m.status }}</span>
            <span v-else-if="m.error" class="perf-err">ERR</span>
            <span class="perf-time" :class="colorForDuration(m.durationMs)">{{ Math.round(m.durationMs) }}ms</span>
          </template>
          <template v-else-if="m.type === 'mount'">
            <span class="perf-method text-purple-300">MOUNT</span>
            <span class="perf-url">{{ m.component }}</span>
            <span class="perf-time" :class="colorForDuration(m.durationMs)">{{ Math.round(m.durationMs) }}ms</span>
          </template>
          <template v-else-if="m.type === 'nav'">
            <span class="perf-method text-blue-300">NAV</span>
            <span class="perf-url">{{ m.metric }}</span>
            <span class="perf-time" :class="colorForDuration(m.durationMs)">{{ Math.round(m.durationMs) }}ms</span>
          </template>
        </div>
        <div v-if="filtered.length === 0" class="perf-empty">
          Sin mediciones todavía. Navega por la app para verlas aquí.
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.perf-widget {
  position: fixed;
  bottom: 1rem;
  right: 1rem;
  width: 420px;
  max-height: 60vh;
  background: rgba(15, 23, 42, 0.95);
  color: #e2e8f0;
  border: 1px solid #334155;
  border-radius: 0.5rem;
  font-family: ui-monospace, 'SF Mono', Menlo, Monaco, Consolas, monospace;
  font-size: 11px;
  z-index: 999999;
  display: flex;
  flex-direction: column;
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
  backdrop-filter: blur(6px);
}

.perf-widget.is-minimized {
  width: auto;
  max-height: none;
}

.perf-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 0.5rem 0.75rem;
  border-bottom: 1px solid #334155;
  background: rgba(30, 41, 59, 0.6);
  border-top-left-radius: 0.5rem;
  border-top-right-radius: 0.5rem;
}

.is-minimized .perf-header {
  border-bottom: none;
  border-radius: 0.5rem;
}

.perf-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 11px;
}

.perf-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 6px #10b981;
}

.perf-stats {
  color: #94a3b8;
  font-weight: normal;
}

.perf-errors {
  color: #f87171;
}

.perf-actions {
  display: flex;
  gap: 0.25rem;
}

.perf-btn {
  background: transparent;
  border: 1px solid #475569;
  color: #cbd5e1;
  width: 22px;
  height: 22px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  line-height: 1;
  display: flex;
  align-items: center;
  justify-content: center;
}

.perf-btn:hover {
  background: #334155;
}

.perf-body {
  display: flex;
  flex-direction: column;
  flex: 1;
  overflow: hidden;
}

.perf-filters {
  display: flex;
  gap: 0.25rem;
  padding: 0.5rem 0.75rem 0.25rem;
}

.perf-filter {
  background: transparent;
  border: 1px solid #475569;
  color: #94a3b8;
  padding: 2px 8px;
  border-radius: 4px;
  cursor: pointer;
  font-size: 10px;
  text-transform: uppercase;
}

.perf-filter.active {
  background: #334155;
  color: #f1f5f9;
  border-color: #64748b;
}

.perf-list {
  flex: 1;
  overflow-y: auto;
  padding: 0.25rem 0.5rem 0.5rem;
}

.perf-row {
  display: grid;
  grid-template-columns: 55px 1fr auto auto;
  gap: 0.5rem;
  padding: 3px 4px;
  border-bottom: 1px solid rgba(51, 65, 85, 0.4);
  align-items: baseline;
}

.perf-row:hover {
  background: rgba(51, 65, 85, 0.3);
}

.perf-method {
  font-weight: bold;
  text-align: right;
}

.perf-url {
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  color: #e2e8f0;
}

.perf-status {
  color: #94a3b8;
  font-size: 10px;
}

.perf-status.perf-err,
.perf-err {
  color: #f87171;
}

.perf-time {
  font-variant-numeric: tabular-nums;
  font-weight: 600;
  min-width: 50px;
  text-align: right;
}

.perf-empty {
  text-align: center;
  color: #64748b;
  padding: 1.5rem 0.5rem;
  font-size: 11px;
}
</style>
