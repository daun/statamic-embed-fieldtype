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
            v-if="embedHtml"
            v-html="embedHtml"
            class="relative aspect-(--embed-ratio) overflow-hidden rounded-lg [&_iframe]:w-full [&_iframe]:h-full [&_iframe]:border-0"
            :style="`--embed-ratio: ${embedRatio}`"
        ></div>
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
            return this.info?.html;
        },
        embedRatio() {
            const width = this.info?.width || 16;
            const height = this.info?.height || 9;
            return width / height;
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
