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
        <div
            v-if="info.html"
            v-html="info.html"
            class="relative aspect-(--embed-ratio) overflow-hidden rounded-lg [&_iframe]:w-full [&_iframe]:h-full [&_iframe]:border-0"
            :style="`--embed-ratio: ${embedRatio}`"
        ></div>
        <article
            v-else-if="info.title"
            class="overflow-hidden bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded-lg"
        >
            <img
                v-if="info.image"
                :src="info.image"
                class="w-full h-auto"
            />
            <div class="px-3 py-2.5 grid gap-0.5 text-sm">
                <p class="line-clamp-1 font-semibold text-gray-800 dark:text-gray-200">
                    <a :href="info.url">{{ info.title }}</a>
                </p>
                <p v-if="info.description" class="line-clamp-1 text-gray-500 dark:text-gray-400">
                    {{ info.description }}
                </p>
                <p v-if="info.provider_url" class="text-gray-400 dark:text-gray-600">
                    {{ formatHostname(info.provider_url) }}
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
        embedHtml() {
            return this.info.html;
        },
        embedRatio() {
            const width = this.info.width;
            const height = this.info.height;

            return (width && height)
                ? (width / height)
                : (16 / 9);
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
