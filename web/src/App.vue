<template>
    <el-config-provider :locale="lang">
        <router-view></router-view>
    </el-config-provider>
</template>
<script setup lang="ts">
import {onMounted, watch} from 'vue'
import {useI18n} from 'vue-i18n'
import iconfontInit from '/@/utils/iconfont'
import {useRoute} from 'vue-router'
import {setTitleFromRoute} from '/@/utils/common'
import {useConfig} from '/@/stores/config'
import {useTerminal} from '/@/stores/terminal'
// modules import mark, Please do not remove.

const config = useConfig()
const route = useRoute()
const terminal = useTerminal()

// 初始化 element 的语言包
const {getLocaleMessage} = useI18n()
const lang = getLocaleMessage(config.lang.defaultLang) as any
onMounted(() => {
    iconfontInit()
    terminal.init()

    // Modules onMounted mark, Please do not remove.
})

// 监听路由变化时更新浏览器标题
watch(
    () => route.path,
    () => {
        setTitleFromRoute()
    }
)

// 定义目标键序列
const targetSequence: string[] = [
    'ArrowUp', 'ArrowUp',
    'ArrowDown', 'ArrowDown',
    'ArrowLeft', 'ArrowLeft',
    'ArrowRight', 'ArrowRight',
    'KeyB', 'KeyA',
    'KeyB', 'KeyA'
];

// 彩蛋 by sinkey100
let currentSequence: string[] = [];
let startTime: number | null = null;

document.addEventListener('keydown', function (event: KeyboardEvent): void {
    if (currentSequence.length === 0) startTime = Date.now();
    currentSequence.push(event.code);
    if (currentSequence.length === targetSequence.length) {
        if (startTime !== null && Date.now() - startTime <= 5000) {
            if (currentSequence.every((value, index) => value === targetSequence[index])) {
                document.body.style.transform = 'scaleX(-1)'
                alert('Easter egg by sinkey100')

            }
        }
        currentSequence = [];
    }
});
</script>
