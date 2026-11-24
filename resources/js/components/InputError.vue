<script setup lang="ts">
defineProps<{
    message?: string;
}>();
</script>

<template>
    <div v-show="message">
        <p class="text-sm text-danger" role="alert">
            {{ message }}
        </p>
    </div>
</template>
