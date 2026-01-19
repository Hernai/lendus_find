<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { v2 } from '@/services/v2'
import type { V2StaffUser } from '@/services/v2/user.staff.service'
import { AppButton } from '@/components/common'
import { useToast } from '@/composables'
import { getErrorMessage, type AxiosErrorResponse } from '@/types/api'
import { logger } from '@/utils/logger'

const log = logger.child('AdminUsers')
const toast = useToast()

// Use V2 StaffUser type
type User = V2StaffUser

// Role labels in Spanish
const roleLabels: Record<string, string> = {
  SUPER_ADMIN: 'Super Admin',
  ADMIN: 'Administrador',
  ANALYST: 'Analista',
  SUPERVISOR: 'Supervisor',
  VIEWER: 'Visor'
}

const getRoleLabel = (role: string) => roleLabels[role] || role

// Filters
const searchQuery = ref('')
const roleFilter = ref('')
const activeFilter = ref('active')
const currentPage = ref(1)
const itemsPerPage = ref(20)

// Data
const users = ref<User[]>([])
const totalItems = ref(0)
const totalPages = ref(1)
const isLoading = ref(true)
const error = ref('')

// Modal state
const showUserModal = ref(false)
const editingUser = ref<User | null>(null)
const isSubmitting = ref(false)
const formError = ref('')

// Delete state
const showDeleteModal = ref(false)
const userToDelete = ref<User | null>(null)
const isDeleting = ref(false)

// Form state
const form = ref({
  name: '',
  email: '',
  phone: '',
  role: 'SUPERVISOR',
  password: '',
  password_confirmation: '',
  is_active: true
})

const formErrors = ref({
  name: '',
  email: '',
  phone: '',
  role: '',
  password: '',
  password_confirmation: ''
})

// Password management
const showChangePassword = ref(false)
const showPassword = ref(false)

// Password strength calculation
const passwordStrength = computed(() => {
  const password = form.value.password
  if (!password) return { score: 0, label: '', color: '' }

  let score = 0

  // Length
  if (password.length >= 8) score += 1
  if (password.length >= 12) score += 1

  // Has lowercase
  if (/[a-z]/.test(password)) score += 1

  // Has uppercase
  if (/[A-Z]/.test(password)) score += 1

  // Has numbers
  if (/[0-9]/.test(password)) score += 1

  // Has special chars
  if (/[^a-zA-Z0-9]/.test(password)) score += 1

  if (score <= 2) return { score, label: 'Débil', color: 'bg-red-500' }
  if (score <= 4) return { score, label: 'Media', color: 'bg-yellow-500' }
  return { score, label: 'Fuerte', color: 'bg-green-500' }
})

// Generate random password
const generatePassword = () => {
  const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*'
  let password = ''
  for (let i = 0; i < 12; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  form.value.password = password
  form.value.password_confirmation = password
  showPassword.value = true // Show so user can see generated password
}

// Role options
const roleFilterOptions = [
  { value: '', label: 'Todos los roles' },
  { value: 'SUPERVISOR', label: 'Supervisor' },
  { value: 'ANALYST', label: 'Analista' },
  { value: 'ADMIN', label: 'Administrador' }
]

const roleOptions = [
  { value: 'SUPERVISOR', label: 'Supervisor' },
  { value: 'ANALYST', label: 'Analista' },
  { value: 'ADMIN', label: 'Administrador' }
]

const activeFilterOptions = [
  { value: '', label: 'Todos' },
  { value: 'active', label: 'Activos' },
  { value: 'inactive', label: 'Inactivos' }
]

// Fetch users
const fetchUsers = async () => {
  isLoading.value = true
  error.value = ''

  try {
    const filters: {
      page?: number
      per_page?: number
      role?: string
      search?: string
      active?: boolean
    } = {
      page: currentPage.value,
      per_page: itemsPerPage.value
    }

    if (roleFilter.value) {
      filters.role = roleFilter.value
    }

    if (searchQuery.value) {
      filters.search = searchQuery.value
    }

    if (activeFilter.value === 'active') {
      filters.active = true
    } else if (activeFilter.value === 'inactive') {
      filters.active = false
    }

    const response = await v2.staff.user.list(filters)

    users.value = response.data
    totalItems.value = response.meta.total
    totalPages.value = response.meta.last_page
  } catch (e) {
    log.error('Error al cargar usuarios', { error: e })
    error.value = 'Error al cargar los usuarios'
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchUsers()
})

// Watch filters and refetch
watch([roleFilter, searchQuery, activeFilter], () => {
  currentPage.value = 1
  fetchUsers()
})

watch(currentPage, () => {
  fetchUsers()
})

// Formatters
const formatDateOnly = (dateStr?: string | null) => {
  if (!dateStr) return 'Nunca'
  return new Date(dateStr).toLocaleDateString('es-MX', {
    day: 'numeric',
    month: 'short'
  })
}

const formatTimeOnly = (dateStr?: string | null) => {
  if (!dateStr) return ''
  return new Date(dateStr).toLocaleTimeString('es-MX', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getRoleBadge = (type: string) => {
  const badges: Record<string, { bg: string; text: string }> = {
    SUPER_ADMIN: { bg: 'bg-purple-100', text: 'text-purple-800' },
    ADMIN: { bg: 'bg-red-100', text: 'text-red-800' },
    ANALYST: { bg: 'bg-blue-100', text: 'text-blue-800' },
    SUPERVISOR: { bg: 'bg-green-100', text: 'text-green-800' }
  }
  return badges[type] || { bg: 'bg-gray-100', text: 'text-gray-800' }
}

// Modal methods
const openCreateModal = () => {
  editingUser.value = null
  form.value = {
    name: '',
    email: '',
    phone: '',
    role: 'SUPERVISOR',
    password: '',
    password_confirmation: '',
    is_active: true
  }
  formErrors.value = { name: '', email: '', phone: '', role: '', password: '', password_confirmation: '' }
  formError.value = ''
  showChangePassword.value = true // Always show for new users
  showPassword.value = false
  showUserModal.value = true
}

// Format phone number for display
const formatPhoneForDisplay = (phone: string | undefined | null): string => {
  if (!phone) return ''
  const digits = phone.replace(/\D/g, '')
  if (digits.length === 10) {
    return `(${digits.slice(0, 2)}) ${digits.slice(2, 6)}-${digits.slice(6)}`
  }
  return phone
}

const openEditModal = (user: User) => {
  editingUser.value = user
  form.value = {
    name: user.name,
    email: user.email,
    phone: formatPhoneForDisplay(user.phone),
    role: user.role,
    password: '',
    password_confirmation: '',
    is_active: user.is_active
  }
  formErrors.value = { name: '', email: '', phone: '', role: '', password: '', password_confirmation: '' }
  formError.value = ''
  showChangePassword.value = false // Don't show password fields by default when editing
  showPassword.value = false
  showUserModal.value = true
}

const validateForm = () => {
  let isValid = true
  formErrors.value = { name: '', email: '', phone: '', role: '', password: '', password_confirmation: '' }

  if (!form.value.name.trim()) {
    formErrors.value.name = 'El nombre es requerido'
    isValid = false
  }

  if (!form.value.email.trim()) {
    formErrors.value.email = 'El email es requerido'
    isValid = false
  } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.value.email)) {
    formErrors.value.email = 'El email no es válido'
    isValid = false
  }

  if (!form.value.role) {
    formErrors.value.role = 'El rol es requerido'
    isValid = false
  }

  // Validate phone format (10 digits)
  if (form.value.phone) {
    const cleanPhone = form.value.phone.replace(/\D/g, '')
    if (cleanPhone.length !== 10) {
      formErrors.value.phone = 'El teléfono debe tener 10 dígitos'
      isValid = false
    }
  }

  // Password validation
  const needsPassword = !editingUser.value || showChangePassword.value

  if (needsPassword) {
    if (!editingUser.value && !form.value.password) {
      // New user requires password
      formErrors.value.password = 'La contraseña es requerida'
      isValid = false
    } else if (form.value.password) {
      // Validate password if provided
      if (form.value.password.length < 8) {
        formErrors.value.password = 'La contraseña debe tener al menos 8 caracteres'
        isValid = false
      } else if (form.value.password !== form.value.password_confirmation) {
        formErrors.value.password_confirmation = 'Las contraseñas no coinciden'
        isValid = false
      }
    }
  }

  return isValid
}

// Get digits count from phone
const getPhoneDigits = (phone: string): string => {
  return phone.replace(/\D/g, '')
}

// Prevent typing more than 10 digits
const handlePhoneKeydown = (event: KeyboardEvent) => {
  const digits = getPhoneDigits(form.value.phone)
  // Allow: backspace, delete, tab, escape, enter, arrows
  const allowedKeys = ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown']
  if (allowedKeys.includes(event.key)) return
  // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
  if (event.ctrlKey || event.metaKey) return
  // Block if already 10 digits and trying to add more
  if (digits.length >= 10 && /^\d$/.test(event.key)) {
    event.preventDefault()
  }
}

// Format phone number as user types
const formatPhone = (event: Event) => {
  const input = event.target as HTMLInputElement
  // Remove non-digits
  let value = input.value.replace(/\D/g, '')
  // Limit to 10 digits
  value = value.slice(0, 10)
  // Format as (XX) XXXX-XXXX
  if (value.length > 6) {
    value = `(${value.slice(0, 2)}) ${value.slice(2, 6)}-${value.slice(6)}`
  } else if (value.length > 2) {
    value = `(${value.slice(0, 2)}) ${value.slice(2)}`
  } else if (value.length > 0) {
    value = `(${value}`
  }
  form.value.phone = value
  // Force input value update to prevent extra characters
  input.value = value
}

const saveUser = async () => {
  if (!validateForm()) return

  isSubmitting.value = true
  formError.value = ''

  try {
    // Clean phone to digits only before sending
    const cleanPhone = form.value.phone ? form.value.phone.replace(/\D/g, '') : undefined

    if (editingUser.value) {
      // Update existing user
      const updatePayload: {
        email?: string
        first_name?: string
        last_name?: string
        phone?: string
        role?: 'ANALYST' | 'SUPERVISOR' | 'ADMIN' | 'SUPER_ADMIN'
        password?: string
        is_active?: boolean
      } = {
        email: form.value.email,
        is_active: form.value.is_active
      }

      // Parse name into first_name and last_name
      const nameParts = form.value.name.trim().split(' ')
      updatePayload.first_name = nameParts[0] || ''
      updatePayload.last_name = nameParts.slice(1).join(' ') || ''

      if (cleanPhone) {
        updatePayload.phone = cleanPhone
      }

      updatePayload.role = form.value.role as 'ANALYST' | 'SUPERVISOR' | 'ADMIN' | 'SUPER_ADMIN'

      // Only include password when changing password
      if (showChangePassword.value && form.value.password) {
        updatePayload.password = form.value.password
      }

      await v2.staff.user.update(editingUser.value.id, updatePayload)
    } else {
      // Create new user
      const nameParts = form.value.name.trim().split(' ')
      const createPayload = {
        email: form.value.email,
        first_name: nameParts[0] || '',
        last_name: nameParts.slice(1).join(' ') || nameParts[0] || '',
        phone: cleanPhone,
        role: form.value.role as 'ANALYST' | 'SUPERVISOR' | 'ADMIN' | 'SUPER_ADMIN',
        password: form.value.password || undefined
      }

      await v2.staff.user.create(createPayload)
    }

    showUserModal.value = false
    await fetchUsers()
  } catch (e: unknown) {
    log.error('Error al guardar usuario', { error: e })
    // Clear previous errors first
    formErrors.value = { name: '', email: '', phone: '', role: '', password: '', password_confirmation: '' }
    formError.value = ''

    // Extract error data safely - handle both direct 422 response and wrapped errors
    interface ValidationError {
      errors?: Record<string, string | string[]>
      message?: string
    }

    const axiosErr = e as AxiosErrorResponse
    const errorData: ValidationError = (
      // Check if it's a direct validation error object (422 from interceptor)
      (e && typeof e === 'object' && 'errors' in e)
        ? e as ValidationError
        // Otherwise try response.data
        : axiosErr?.response?.data ?? {}
    )

    // Process field-specific errors
    if (errorData.errors && typeof errorData.errors === 'object') {
      const errors = errorData.errors
      const getFirstError = (field: string | string[] | undefined): string => {
        if (!field) return ''
        return Array.isArray(field) ? (field[0] ?? '') : field
      }
      formErrors.value.email = getFirstError(errors.email)
      formErrors.value.name = getFirstError(errors.name)
      formErrors.value.phone = getFirstError(errors.phone)
      formErrors.value.password = getFirstError(errors.password)
      formErrors.value.role = getFirstError(errors.role)
    }

    // Show general error message (translate common messages to Spanish)
    if (errorData.message) {
      const messageTranslations: Record<string, string> = {
        'Validation error': 'Error de validación',
        'User not found': 'Usuario no encontrado',
        'You cannot change your own admin role': 'No puedes cambiar tu propio rol de administrador',
        'You cannot delete your own account': 'No puedes eliminar tu propia cuenta',
        'Cannot delete user with assigned applications. Reassign or deactivate instead.': 'No se puede eliminar un usuario con solicitudes asignadas. Reasigna o desactiva en su lugar.'
      }
      formError.value = messageTranslations[errorData.message] || errorData.message
    } else if (!errorData.errors) {
      formError.value = 'Error al guardar el usuario'
    }
  } finally {
    isSubmitting.value = false
  }
}

// Delete methods
const openDeleteModal = (user: User) => {
  userToDelete.value = user
  showDeleteModal.value = true
}

const confirmDelete = async () => {
  if (!userToDelete.value) return

  isDeleting.value = true

  try {
    await v2.staff.user.remove(userToDelete.value.id)
    showDeleteModal.value = false
    userToDelete.value = null
    await fetchUsers()
  } catch (e: unknown) {
    log.error('Error al eliminar usuario', { error: e })
    toast.error(getErrorMessage(e, 'Error al eliminar el usuario'))
  } finally {
    isDeleting.value = false
  }
}

// Toggle active status
const toggleActiveStatus = async (user: User) => {
  try {
    await v2.staff.user.update(user.id, {
      is_active: !user.is_active
    })
    await fetchUsers()
  } catch (e: unknown) {
    log.error('Error al cambiar estado del usuario', { error: e })
    toast.error(getErrorMessage(e, 'Error al cambiar el estado del usuario'))
  }
}

const clearFilters = () => {
  searchQuery.value = ''
  roleFilter.value = ''
  activeFilter.value = 'active'
  currentPage.value = 1
}

// Pagination
const paginationRange = computed(() => {
  const range: number[] = []
  const maxVisible = 5

  if (totalPages.value <= maxVisible) {
    for (let i = 1; i <= totalPages.value; i++) {
      range.push(i)
    }
  } else {
    const start = Math.max(1, currentPage.value - 2)
    const end = Math.min(totalPages.value, start + maxVisible - 1)

    for (let i = start; i <= end; i++) {
      range.push(i)
    }
  }

  return range
})
</script>

<template>
  <div class="p-4 md:p-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-gray-900">Usuarios</h1>
        <p class="text-gray-500 text-sm mt-1">Gestiona los usuarios del sistema</p>
      </div>
      <AppButton variant="primary" @click="openCreateModal">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
        </svg>
        Crear Usuario
      </AppButton>
    </div>

    <!-- Filters -->
    <div class="bg-white rounded-xl shadow-sm p-4 mb-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Search -->
        <div class="sm:col-span-2 lg:col-span-1">
          <div class="relative">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
            </svg>
            <input
              v-model="searchQuery"
              type="text"
              placeholder="Buscar por nombre o email..."
              class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
            />
          </div>
        </div>

        <!-- Role Filter -->
        <div>
          <select
            v-model="roleFilter"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          >
            <option v-for="opt in roleFilterOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <!-- Active Filter -->
        <div>
          <select
            v-model="activeFilter"
            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
          >
            <option v-for="opt in activeFilterOptions" :key="opt.value" :value="opt.value">
              {{ opt.label }}
            </option>
          </select>
        </div>

        <!-- Clear Filters -->
        <div class="flex items-center">
          <button
            v-if="searchQuery || roleFilter || activeFilter !== 'active'"
            class="text-sm text-primary-600 hover:text-primary-700"
            @click="clearFilters"
          >
            Limpiar filtros
          </button>
        </div>
      </div>
    </div>

    <!-- Error State -->
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
      <p class="text-red-800">{{ error }}</p>
      <button class="text-sm text-red-600 underline mt-2" @click="fetchUsers">
        Reintentar
      </button>
    </div>

    <!-- Users Table -->
    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
      <!-- Loading State -->
      <div v-if="isLoading" class="p-8 text-center">
        <div class="animate-spin w-8 h-8 border-4 border-primary-600 border-t-transparent rounded-full mx-auto mb-4" />
        <p class="text-gray-500">Cargando usuarios...</p>
      </div>

      <!-- Empty State -->
      <div v-else-if="users.length === 0" class="p-8 text-center">
        <svg class="w-12 h-12 text-gray-300 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
        </svg>
        <p class="text-gray-500">No hay usuarios que coincidan con los filtros</p>
      </div>

      <!-- Table -->
      <div v-else class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
          <thead class="bg-gray-50">
            <tr>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Usuario
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Teléfono
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Rol
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider hidden md:table-cell">
                Último acceso
              </th>
              <th class="px-3 py-2 text-left text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Estado
              </th>
              <th class="px-3 py-2 text-right text-[11px] font-medium text-gray-500 uppercase tracking-wider">
                Acciones
              </th>
            </tr>
          </thead>
          <tbody class="bg-white divide-y divide-gray-200">
            <tr
              v-for="user in users"
              :key="user.id"
              class="hover:bg-gray-50 transition-colors"
            >
              <td class="px-3 py-2">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-full bg-primary-100 flex items-center justify-center text-primary-700 font-medium text-sm flex-shrink-0">
                    {{ user.name.charAt(0).toUpperCase() }}
                  </div>
                  <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate">{{ user.name }}</p>
                    <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
                  </div>
                </div>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <span class="text-sm text-gray-600">{{ formatPhoneForDisplay(user.phone) || '-' }}</span>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <span
                  class="px-2 py-0.5 rounded-full text-[11px] font-medium"
                  :class="[getRoleBadge(user.role).bg, getRoleBadge(user.role).text]"
                >
                  {{ getRoleLabel(user.role) }}
                </span>
              </td>
              <td class="px-3 py-2 whitespace-nowrap hidden md:table-cell">
                <div class="text-xs text-gray-900">
                  {{ formatDateOnly(user.last_login_at) }}
                  <span v-if="user.last_login_at" class="text-gray-500">{{ formatTimeOnly(user.last_login_at) }}</span>
                </div>
              </td>
              <td class="px-3 py-2 whitespace-nowrap">
                <button
                  class="px-2 py-0.5 rounded-full text-[11px] font-medium transition-colors"
                  :class="user.is_active
                    ? 'bg-green-100 text-green-800 hover:bg-green-200'
                    : 'bg-gray-100 text-gray-800 hover:bg-gray-200'"
                  @click="toggleActiveStatus(user)"
                >
                  {{ user.is_active ? 'Activo' : 'Inactivo' }}
                </button>
              </td>
              <td class="px-3 py-2 whitespace-nowrap text-right">
                <div class="flex items-center justify-end gap-1">
                  <button
                    class="p-1.5 text-gray-400 hover:text-primary-600 hover:bg-primary-50 rounded-lg transition-colors"
                    title="Editar"
                    @click="openEditModal(user)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                  </button>
                  <button
                    class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors"
                    title="Eliminar"
                    @click="openDeleteModal(user)"
                  >
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                    </svg>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="totalPages > 1" class="bg-gray-50 px-4 py-3 flex items-center justify-between border-t border-gray-200">
        <div class="flex-1 flex justify-between sm:hidden">
          <button
            :disabled="currentPage === 1"
            class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="currentPage--"
          >
            Anterior
          </button>
          <button
            :disabled="currentPage === totalPages"
            class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
            @click="currentPage++"
          >
            Siguiente
          </button>
        </div>
        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
          <div>
            <p class="text-sm text-gray-700">
              Mostrando
              <span class="font-medium">{{ (currentPage - 1) * itemsPerPage + 1 }}</span>
              a
              <span class="font-medium">{{ Math.min(currentPage * itemsPerPage, totalItems) }}</span>
              de
              <span class="font-medium">{{ totalItems }}</span>
              resultados
            </p>
          </div>
          <div>
            <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
              <button
                :disabled="currentPage === 1"
                class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="currentPage--"
              >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd" />
                </svg>
              </button>
              <button
                v-for="page in paginationRange"
                :key="page"
                :class="[
                  'relative inline-flex items-center px-4 py-2 border text-sm font-medium',
                  currentPage === page
                    ? 'z-10 bg-primary-50 border-primary-500 text-primary-600'
                    : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'
                ]"
                @click="currentPage = page"
              >
                {{ page }}
              </button>
              <button
                :disabled="currentPage === totalPages"
                class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
                @click="currentPage++"
              >
                <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
                  <path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd" />
                </svg>
              </button>
            </nav>
          </div>
        </div>
      </div>
    </div>

    <!-- Create/Edit User Modal -->
    <div
      v-if="showUserModal"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showUserModal = false"
    >
      <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">
          {{ editingUser ? 'Editar Usuario' : 'Crear Usuario' }}
        </h3>

        <form @submit.prevent="saveUser" class="space-y-4">
          <!-- General Error -->
          <div v-if="formError" class="bg-red-50 border border-red-200 rounded-lg p-3">
            <p class="text-sm text-red-800">{{ formError }}</p>
          </div>

          <!-- Name -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nombre *</label>
            <input
              v-model="form.name"
              type="text"
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              :class="formErrors.name ? 'border-red-300' : 'border-gray-300'"
              placeholder="Nombre completo"
            />
            <p v-if="formErrors.name" class="text-sm text-red-600 mt-1">{{ formErrors.name }}</p>
          </div>

          <!-- Email -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
            <input
              v-model="form.email"
              type="email"
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              :class="formErrors.email ? 'border-red-300' : 'border-gray-300'"
              placeholder="correo@ejemplo.com"
            />
            <p v-if="formErrors.email" class="text-sm text-red-600 mt-1">{{ formErrors.email }}</p>
          </div>

          <!-- Phone -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Teléfono</label>
            <input
              :value="form.phone"
              type="tel"
              maxlength="15"
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              :class="formErrors.phone ? 'border-red-300' : 'border-gray-300'"
              placeholder="(55) 1234-5678"
              @keydown="handlePhoneKeydown"
              @input="formatPhone"
            />
            <p class="text-xs text-gray-500 mt-1">10 dígitos</p>
            <p v-if="formErrors.phone" class="text-sm text-red-600 mt-1">{{ formErrors.phone }}</p>
          </div>

          <!-- Role -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Rol *</label>
            <select
              v-model="form.role"
              class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
              :class="formErrors.role ? 'border-red-300' : 'border-gray-300'"
            >
              <option v-for="opt in roleOptions" :key="opt.value" :value="opt.value">
                {{ opt.label }}
              </option>
            </select>
            <p v-if="formErrors.role" class="text-sm text-red-600 mt-1">{{ formErrors.role }}</p>
          </div>

          <!-- Password Section -->
          <div class="space-y-3">
            <!-- Toggle for editing mode -->
            <div v-if="editingUser && !showChangePassword" class="flex items-center justify-between">
              <span class="text-sm text-gray-500">Contraseña actual mantenida</span>
              <button
                type="button"
                class="text-sm text-primary-600 hover:text-primary-700 font-medium"
                @click="showChangePassword = true"
              >
                Cambiar contraseña
              </button>
            </div>

            <!-- Password fields -->
            <div v-if="showChangePassword" class="space-y-3">
              <div class="flex items-center justify-between">
                <label class="block text-sm font-medium text-gray-700">
                  {{ editingUser ? 'Nueva contraseña' : 'Contraseña *' }}
                </label>
                <div class="flex items-center gap-2">
                  <button
                    type="button"
                    class="text-xs text-primary-600 hover:text-primary-700 font-medium"
                    @click="generatePassword"
                  >
                    Generar
                  </button>
                  <span class="text-gray-300">|</span>
                  <button
                    type="button"
                    class="text-xs text-gray-500 hover:text-gray-700"
                    @click="showPassword = !showPassword"
                  >
                    {{ showPassword ? 'Ocultar' : 'Mostrar' }}
                  </button>
                </div>
              </div>

              <!-- Password input -->
              <div class="relative">
                <input
                  v-model="form.password"
                  :type="showPassword ? 'text' : 'password'"
                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent pr-10"
                  :class="formErrors.password ? 'border-red-300' : 'border-gray-300'"
                  placeholder="Mínimo 8 caracteres"
                />
                <button
                  type="button"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                  @click="showPassword = !showPassword"
                >
                  <svg v-if="showPassword" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                  </svg>
                  <svg v-else class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                  </svg>
                </button>
              </div>

              <!-- Password strength -->
              <div v-if="form.password" class="space-y-1">
                <div class="flex gap-1">
                  <div
                    v-for="i in 6"
                    :key="i"
                    class="h-1 flex-1 rounded-full transition-colors"
                    :class="i <= passwordStrength.score ? passwordStrength.color : 'bg-gray-200'"
                  />
                </div>
                <p class="text-xs" :class="{
                  'text-red-600': passwordStrength.score <= 2,
                  'text-yellow-600': passwordStrength.score > 2 && passwordStrength.score <= 4,
                  'text-green-600': passwordStrength.score > 4
                }">
                  Seguridad: {{ passwordStrength.label }}
                </p>
              </div>

              <p v-if="formErrors.password" class="text-sm text-red-600">{{ formErrors.password }}</p>

              <!-- Confirm password -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña</label>
                <input
                  v-model="form.password_confirmation"
                  :type="showPassword ? 'text' : 'password'"
                  class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-transparent"
                  :class="formErrors.password_confirmation ? 'border-red-300' : 'border-gray-300'"
                  placeholder="Repetir contraseña"
                />
                <p v-if="formErrors.password_confirmation" class="text-sm text-red-600 mt-1">{{ formErrors.password_confirmation }}</p>
              </div>

              <!-- Cancel change password (only when editing) -->
              <button
                v-if="editingUser"
                type="button"
                class="text-sm text-gray-500 hover:text-gray-700"
                @click="showChangePassword = false; form.password = ''; form.password_confirmation = ''"
              >
                Cancelar cambio de contraseña
              </button>
            </div>
          </div>

          <!-- Active -->
          <div class="flex items-center gap-2">
            <input
              v-model="form.is_active"
              type="checkbox"
              id="is_active"
              class="w-4 h-4 text-primary-600 border-gray-300 rounded focus:ring-primary-500"
            />
            <label for="is_active" class="text-sm text-gray-700">Usuario activo</label>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-4">
            <AppButton
              type="button"
              variant="outline"
              class="flex-1"
              @click="showUserModal = false"
            >
              Cancelar
            </AppButton>
            <AppButton
              type="submit"
              variant="primary"
              class="flex-1"
              :loading="isSubmitting"
            >
              {{ editingUser ? 'Guardar' : 'Crear' }}
            </AppButton>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div
      v-if="showDeleteModal && userToDelete"
      class="fixed inset-0 bg-black/50 flex items-center justify-center z-50"
      @click.self="showDeleteModal = false"
    >
      <div class="bg-white rounded-xl p-6 w-full max-w-md mx-4">
        <div class="flex items-center gap-4 mb-4">
          <div class="w-12 h-12 rounded-full bg-red-100 flex items-center justify-center">
            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
          </div>
          <div>
            <h3 class="text-lg font-semibold text-gray-900">Eliminar Usuario</h3>
            <p class="text-sm text-gray-500">Esta acción no se puede deshacer</p>
          </div>
        </div>

        <p class="text-gray-700 mb-6">
          ¿Estás seguro de que deseas eliminar al usuario <strong>{{ userToDelete.name }}</strong>?
        </p>

        <div class="flex gap-3">
          <AppButton
            variant="outline"
            class="flex-1"
            @click="showDeleteModal = false"
          >
            Cancelar
          </AppButton>
          <AppButton
            variant="danger"
            class="flex-1"
            :loading="isDeleting"
            @click="confirmDelete"
          >
            Eliminar
          </AppButton>
        </div>
      </div>
    </div>
  </div>
</template>
