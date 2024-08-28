<template>
    <div class="default-main">
        <div class="tips">
            {{ adminInfo.nickname }}, Welcome back! 🎉
        </div>

    </div>
</template>

<script setup lang="ts">
import { useAdminInfo } from '/@/stores/adminInfo'

defineOptions({
    name: 'dashboard',
})
const adminInfo = useAdminInfo()
</script>

<style scoped lang="scss">
.tips{
    font-size:24px;
    text-align: center;
    color:#444;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 70vh;
}
</style>
