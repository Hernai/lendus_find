<script setup lang="ts">
interface Option {
  value: string | number
  label: string
  description?: string
}

interface Props {
  modelValue: string | number | null
  options: Option[]
  label?: string
  error?: string
  required?: boolean
  inline?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  required: false,
  inline: true
})

const emit = defineEmits<{
  'update:modelValue': [value: string | number]
}>()

const handleChange = (value: string | number) => {
  emit('update:modelValue', value)
}
</script>

<template>
  <div class="w-full">
    <!-- Label -->
    <label v-if="label" class="block text-sm font-medium text-gray-700 mb-2">
      {{ label }}
      <span v-if="required" class="text-red-500">*</span>
    </label>

    <!-- Options -->
    <div :class="['flex', inline ? 'flex-row gap-3' : 'flex-col gap-2']">
      <label
        v-for="option in options"
        :key="option.value"
        class="cursor-pointer flex-1"
      >
        <input
          type="radio"
          :value="option.value"
          :checked="modelValue === option.value"
          class="sr-only peer"
          @change="handleChange(option.value)"
        />
        <div
          class="px-4 py-3 border-2 rounded-xl text-center transition-colors duration-200
                 peer-checked:bg-primary-50 peer-checked:border-primary-500 peer-checked:text-primary-600
                 border-gray-200 hover:border-gray-300"
        >
          <span class="font-medium">{{ option.label }}</span>
          <p v-if="option.description" class="text-xs text-gray-500 mt-1">
            {{ option.description }}
          </p>
        </div>
      </label>
    </div>

    <!-- Error message -->
    <p v-if="error" class="mt-1 text-sm text-red-600">
      {{ error }}
    </p>
  </div>
</template>
