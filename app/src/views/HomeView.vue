<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { http } from '@/services/http'
import { endpoints } from '@/services/endpoints'

const message = ref<string>('Loading...')
const error = ref<string | null>(null)

onMounted(async () => {
  try {
    const { data } = await http.get<{ message: string }>(endpoints.hello())
    message.value = data.message
  } catch (e: any) {
    error.value = 'Failed to load message'
  }
})
</script>

<template>
  <main style="padding: 2rem">
    <h1>Home</h1>
    <p v-if="!error">{{ message }}</p>
    <p v-else style="color: red">{{ error }}</p>
  </main>
</template>
