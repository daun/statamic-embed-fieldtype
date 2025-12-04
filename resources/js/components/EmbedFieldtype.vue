<template>
    <div
        class="flex flex-col gap-2"
        :class="{ 'p-2 bg-gray-50 border border-gray-300 dark:bg-transparent dark:border-gray-700 rounded-xl': config.border }"
    >
        <ui-input-group>
            <ui-input-group-prepend v-if="prepend" :text="__(prepend)" />
            <ui-input
                type="url"
                :model-value="value"
                :isReadOnly="isReadOnly"
                :placeholder="__(config.placeholder) || 'https://'"
                :aria-label="__('Content URL')"
                @update:model-value="update"
                @focus="$emit('focus')"
                @blur="$emit('blur')"
                :input-class="prepend ? 'border-s-0' : ''"
            />
        </ui-input-group>
        <article
            v-if="shouldShowPreview && hasPreviewData"
            class="overflow-hidden border border-gray-300 dark:border-gray-700 rounded-lg"
            :class="{
                'bg-white dark:bg-gray-900': config.border,
                'bg-gray-50 dark:bg-gray-900 shadow-ui-sm': !config.border,
                'flex': shouldEnforceImage && info.image
            }"
        >
            <div
                v-if="shouldShowEmbed && info.code"
                :class="{ 'p-2 pb-0': info.code.borderRadius }"
            >
                <div
                    v-html="info.code.html"
                    class="relative overflow-hidden [&_iframe]:w-full! [&_iframe]:max-w-none! [&_iframe]:h-full! [&_iframe]:border-0"
                    :class="{
                        'aspect-(--embed-ratio)': info.code.ratio,
                        'h-(--embed-height)': info.code.height && !info.code.ratio,
                    }"
                    :style="{
                        '--embed-ratio': info.code.ratio,
                        '--embed-width': `${info.code.width ?? 0}px`,
                        '--embed-height': `${info.code.height ?? 0}px`,
                    }"
                ></div>
            </div>

            <img
                v-else-if="shouldShowImage && info.image"
                :src="info.image.url"
                :class="{
                    'w-full h-auto': !shouldEnforceImage,
                    'flex-0 h-24 w-auto max-w-1/3! self-stretch object-cover': shouldEnforceImage
                }"
            />
            <div v-if="shouldShowText && info.title" class="px-4 py-3 grid gap-0.5 text-sm self-start">
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
        prepend() {
            const { prepend = 'URL' } = this.config;
            if (['null', 'false'].includes(prepend)) {
                return null;
            } else {
                return prepend;
            }
        },
        shouldShowPreview() {
            return this.config.preview_type !== 'none';
        },
        shouldShowEmbed() {
            return ['embed'].includes(this.config.preview_type);
        },
        shouldShowImage() {
            return ['embed', 'thumbnail'].includes(this.config.preview_type);
        },
        shouldEnforceImage() {
            return ['thumbnail'].includes(this.config.preview_type);
        },
        shouldShowText() {
            return ['embed', 'thumbnail', 'text'].includes(this.config.preview_type);
        },
        hasPreviewData() {
            return this.info.title || this.info.code || this.info.image;
        },
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
