<script setup>
</script>

<template>
    <router-view v-slot="{ Component, route }">
        <Transition name="page" mode="out-in">
            <component :is="Component" :key="route.name" />
        </Transition>
    </router-view>
</template>
