<template>
    <div class="layout-logo">
        <img class="logo-img" src="~assets/logo.png" alt="logo" />
    </div>
</template>

<script setup lang="ts">
import { useConfig } from '/@/stores/config'
import { useSiteConfig } from '/@/stores/siteConfig'

const config = useConfig()
const siteConfig = useSiteConfig()

</script>

<style scoped lang="scss">
.layout-logo {
    .logo-img{
        width:135px;
        margin:13px 0 0 20px;
    }
}
.fold {
    margin-left: auto;
}
.unfold {
    margin: 0 auto;
}
</style>
