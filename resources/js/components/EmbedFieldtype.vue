<template>
    <div class="flex flex-col space-y-2 p-2 bg-gray-50 border border-gray-300 dark:bg-transparent dark:border-gray-700 rounded-xl">
        <ui-input-group>
            <ui-input-group-prepend :text="__('URL')" />
            <ui-input
                :model-value="value"
                :isReadOnly="isReadOnly"
                :placeholder="__(config.placeholder) || 'https://'"
                :aria-label="__('Content URL')"
                @update:model-value="update"
                @focus="$emit('focus')"
                @blur="$emit('blur')"
                input-class="border-s-0"
            />
        </ui-input-group>
        <article
            v-if="info.title || info.embed || info.thumbnail"
            class="overflow-hidden bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg"
        >
            <div
                v-if="info.embed"
                v-html="info.embed.html"
                class="relative aspect-(--embed-ratio) overflow-hidden [&_iframe]:w-full [&_iframe]:h-full [&_iframe]:border-0"
                :style="`--embed-ratio: ${info.embed.ratio}`"
            ></div>
            <img
                v-else-if="info.thumbnail"
                :src="info.thumbnail.url"
                class="w-full h-auto"
            />
            <div v-if="info.title" class="px-4 py-3 grid gap-0.5 text-sm">
                <p class="line-clamp-1 font-semibold text-gray-800 dark:text-gray-200">
                    <a :href="info.url">{{ info.title }}</a>
                </p>
                <p v-if="info.description || info.author?.name" class="line-clamp-1 text-gray-500 dark:text-gray-400">
                    {{ info.description || info.author?.name }}
                </p>
                <p v-if="info.provider?.url" class="text-gray-400 dark:text-gray-600">
                    {{ formatHostname(info.provider?.url) }}
                </p>
            </div>
        </article>
    </div>
</template>

<script>
import debounce from 'just-debounce-it';
import { FieldtypeMixin as Fieldtype } from '@statamic/cms';

export default {
    mixins: [Fieldtype],
    data() {
        return {
            embedInfo: {}
        };
    },
    computed: {
        info() {
            return this.embedInfo[this.value] || {};
        },
        loadEmbedInfoDebounced() {
            return debounce(this.loadEmbedInfo, 300);
        },
    },
    methods: {
        loadEmbedInfo(url) {
            if (!this.isUrl(url)) return;

            this.$axios
                .post(this.meta.route, { url })
                .then(({ data = {} }) => {
                    this.embedInfo[url] = data
                })
        },
        isUrl(url) {
            if (!url) return false;

            try {
                const { protocol } = new URL(url);
                return ['http:', 'https:'].includes(protocol);
            } catch (_) {
                return false;
            }
        },
        formatHostname(url) {
            try {
                const { hostname } = new URL(url);
                return hostname.replace(/^www\d?\./, '');
            } catch (error) {
                console.error(error);
                return url;
            }
        },
    },
    created() {
        if (this.value && this.meta.info) {
            this.embedInfo[this.value] = this.meta.info;
        }
    },
    watch: {
        value: {
            immediate: true,
            handler(value) {
                setTimeout(() => {
                    this.loadEmbedInfoDebounced(value)
                })
            },
        },
    }
};
</script>
