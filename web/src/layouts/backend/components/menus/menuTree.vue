<template>
    <template v-for="menu in props.menus">
        <template v-if="menu.children && menu.children.length > 0">
            <el-sub-menu @click="onClickSubMenu(menu)" :index="menu.path" :key="menu.path">
                <template #title>
                    <Icon v-if="props.extends.level < 2"
                          :name="menu.meta?.icon ? menu.meta?.icon : config.layout.menuDefaultIcon"/>
                    <span>{{ menu.meta?.title ? menu.meta?.title : $t('noTitle') }}</span>
                </template>
                <menu-tree :extends="{ ...props.extends, level: props.extends.level + 1 }"
                           :menus="menu.children"></menu-tree>
            </el-sub-menu>
        </template>
        <template v-else>
            <el-menu-item :index="menu.path" :key="menu.path" @click="onClickMenu(menu)">
                <Icon v-if="props.extends.level < 2"
                      :name="menu.meta?.icon ? menu.meta?.icon : config.layout.menuDefaultIcon"/>
                <span>{{ menu.meta?.title ? menu.meta?.title : $t('noTitle') }}</span>
            </el-menu-item>
        </template>
    </template>
</template>
<script setup lang="ts">
import {useConfig} from '/@/stores/config'
import type {RouteRecordRaw} from 'vue-router'
import {getFirstRoute, onClickMenu} from '/@/utils/router'
import {ElNotification} from 'element-plus'
import {useI18n} from 'vue-i18n'

const {t} = useI18n()
const config = useConfig()

interface Props {
    menus: RouteRecordRaw[]
    extends?: {
        level: number
        [key: string]: any
    }
}

const props = withDefaults(defineProps<Props>(), {
    menus: () => [],
    extends: () => {
        return {
            level: 1,
        }
    },
})

/**
 * sub-menu-item 被点击 - 用于单栏布局和双栏布局
 * 顶栏菜单：点击时打开第一个菜单
 * 侧边菜单（若有）：点击只展开收缩
 *
 * sub-menu-item 被点击时，也会触发到 menu-item 的点击事件，由 el-menu 内部触发，无法很好的排除，在此检查 level 值
 */
const onClickSubMenu = (menu: RouteRecordRaw) => {
    if (props.extends?.position == 'horizontal' && props.extends.level <= 1 && menu.children?.length) {
        const firstRoute = getFirstRoute(menu.children)
        if (firstRoute) {
            onClickMenu(firstRoute)
        } else {
            ElNotification({
                type: 'error',
                message: t('utils.No child menu to jump to!'),
            })
        }
    }
}
</script>

<style scoped lang="scss">

.el-sub-menu {
    margin: 5px 10px;

    .el-menu-item {
        margin: 4px 0;
    }

    :deep(.el-sub-menu__title) {
        --el-menu-item-height: 40px;
        border-radius: 2px;

        &:hover {
            background-color: #f2f3f5 !important;
        }
    }

    &.is-active{
        > :deep(.el-sub-menu__title) {
            color: var(--el-color-primary);
            font-weight: 500;
            i, .icon{
                color: var(--el-color-primary)!important;
            }
        }
    }
}

.el-menu-item {
    margin: 4px 10px;
    border-radius: 2px;
    --el-menu-level-padding: 35px;
    --el-menu-item-height: 40px;
    --el-menu-sub-item-height: 40px;
}

.el-sub-menu .icon,
.el-menu-item .icon {
    vertical-align: middle;
    margin-right: 10px;
    width: 24px;
    text-align: center;
    flex-shrink: 0;
    color: #86909c !important;
}

.is-active > .icon {
    color: var(--el-menu-active-color) !important;
}

.el-menu-item {
    &.is-active {
        font-weight: 500;
    }

    &.is-active, &:hover {
        background-color: var(--ba-bg-color);
    }
}
</style>
