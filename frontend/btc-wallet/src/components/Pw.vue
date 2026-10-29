<script setup>
import { ref } from 'vue'
defineProps({ modelValue: String, id: String })
defineEmits(['update:modelValue'])
const on = ref(false)
</script>
<template>
  <div class="input">
    <input :id="id" :type="on ? 'text' : 'password'" :value="modelValue" autocomplete="new-password" autocapitalize="none" autocorrect="off" spellcheck="false" required @input="$emit('update:modelValue', $event.target.value)">
    <button type="button" class="eye" @click="on = !on">{{ on ? 'Hide' : 'Show' }}</button>
  </div>
</template>
