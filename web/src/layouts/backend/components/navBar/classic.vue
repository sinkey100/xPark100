<template>
    <div class="nav-bar">
        <Logo v-if="config.layout.menuShowTopBar"/>
        <NavTabs v-if="!config.layout.shrink"/>

        <NavMenus/>
    </div>
</template>

<script setup lang="ts">
import {useConfig} from '/@/stores/config'
import NavMenus from '../navMenus.vue'
import {showShade} from '/@/utils/pageShade'
import Logo from "/@/layouts/backend/components/logo.vue";
import NavTabs from "/@/layouts/backend/components/navBar/tabs.vue";

const config = useConfig()

const onMenuCollapse = () => {
    showShade('ba-aside-menu-shade', () => {
        config.setLayout('menuCollapse', true)
    })
    config.setLayout('menuCollapse', false)
}
</script>

<style scoped lang="scss">
.nav-bar {
    display: flex;
    height: 50px;
    width: 100%;
    background-color: v-bind('config.getColorVal("headerBarBackground")');

    :deep(.nav-tabs) {
        display: flex;
        height: 45px;
        position: relative;
        margin-left: 105px;
        margin-top: 1px;

        .ba-nav-tab {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 20px 0 10px;
            background: #fafafa;
            cursor: pointer;
            border-color: #f0f0f0;
            z-index: 1;
            border-radius: var(--el-border-radius-base);
            height: 100%;
            user-select: none;
            color: v-bind('config.getColorVal("headerBarTabColor")');
            transition: all 0.2s;
            -webkit-transition: all 0.2s;

            .close-icon {
                padding: 2px;
                margin: 2px 0 0 4px;
            }

            .close-icon:hover {
                color: var(--el-color-primary) !important;
            }

            &.active {
                color: v-bind('config.getColorVal("headerBarTabActiveColor")');

                .close-icon:hover {
                    color: #fff !important;
                }
            }

            &:hover {
                background-color: v-bind('config.getColorVal("headerBarHoverBackground")');
            }
        }

        .nav-tabs-active-box {
            position: absolute;
            height: 50px;
            transition: all 0.2s;
            -webkit-transition: all 0.2s;
        }
    }
}

.unfold {
    align-self: center;
    padding-left: var(--ba-main-space);
}
</style>
