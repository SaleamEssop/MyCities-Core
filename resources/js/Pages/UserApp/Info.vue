<template>
  <UserAppLayout title="Information">

    <!-- Empty state -->
    <div v-if="!pages.length" class="info-empty">
      <i class="fas fa-file-alt"></i>
      <p>No information pages available yet.</p>
    </div>

    <template v-else>
      <!-- Root-level page tabs (horizontal scroll) -->
      <div class="info-tab-strip" role="tablist">
        <button
          v-for="(page, idx) in pages"
          :key="page.id"
          class="info-tab-btn"
          :class="{ active: activePageIdx === idx }"
          role="tab"
          @click="selectPage(idx)"
        >
          <i v-if="page.icon" :class="page.icon" class="info-tab-icon"></i>
          {{ page.title }}
        </button>
      </div>

      <!-- Content panel for active root page -->
      <div v-if="activePage" class="info-panel">

        <!-- If the page has children: show child sub-tabs + child content -->
        <template v-if="activePage.children && activePage.children.length">

          <!-- Parent content (if any) shown above child tabs -->
          <div
            v-if="activePage.html_content"
            class="info-content editorjs-content"
            v-html="activePage.html_content"
          ></div>

          <!-- Child sub-tabs -->
          <div class="info-subtab-strip" role="tablist">
            <button
              v-for="(child, cidx) in activePage.children"
              :key="child.id"
              class="info-subtab-btn"
              :class="{ active: activeChildIdx === cidx }"
              role="tab"
              @click="activeChildIdx = cidx"
            >
              <i v-if="child.icon" :class="child.icon" class="info-tab-icon"></i>
              {{ child.title }}
            </button>
          </div>

          <!-- Child content -->
          <div
            v-if="activeChild"
            class="info-content editorjs-content"
            v-html="activeChild.html_content"
          ></div>

        </template>

        <!-- Page without children: show content directly -->
        <template v-else>
          <div
            class="info-content editorjs-content"
            v-html="activePage.html_content || '<p class=\'info-no-content\'>No content available yet.</p>'"
          ></div>
        </template>

      </div>
    </template>

  </UserAppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import UserAppLayout from '@/Layouts/UserAppLayout.vue'

const props = defineProps({
  pages:         { type: Array,  default: () => [] },
  activePageId:  { type: Number, default: null },
  activeChildId: { type: Number, default: null },
})

// Resolve initial active page index from ?tab= query param
function resolveInitialPageIdx() {
  if (props.activePageId) {
    const idx = props.pages.findIndex(p => p.id === props.activePageId)
    if (idx >= 0) return idx
  }
  return 0
}

function resolveInitialChildIdx(pageIdx) {
  if (props.activeChildId) {
    const children = props.pages[pageIdx]?.children ?? []
    const idx = children.findIndex(c => c.id === props.activeChildId)
    if (idx >= 0) return idx
  }
  return 0
}

const activePageIdx  = ref(resolveInitialPageIdx())
const activeChildIdx = ref(resolveInitialChildIdx(activePageIdx.value))

const activePage  = computed(() => props.pages[activePageIdx.value] ?? null)
const activeChild = computed(() => activePage.value?.children?.[activeChildIdx.value] ?? null)

function selectPage(idx) {
  activePageIdx.value  = idx
  activeChildIdx.value = 0
}

watch(activePageIdx, () => { activeChildIdx.value = 0 })
</script>

<style scoped>
/* ── Tab strip ── */
.info-tab-strip {
  display: flex;
  overflow-x: auto;
  background: var(--ua-primary, #009BA4);
  padding: 0 8px;
  gap: 2px;
  flex-shrink: 0;
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.info-tab-strip::-webkit-scrollbar { display: none; }

.info-tab-btn {
  flex-shrink: 0;
  padding: 10px 14px;
  background: none;
  border: none;
  border-bottom: 3px solid transparent;
  color: rgba(255,255,255,0.75);
  font-family: 'Nunito', sans-serif;
  font-size: 0.8rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
  display: flex;
  align-items: center;
  gap: 5px;
}
.info-tab-btn.active {
  color: #fff;
  border-bottom-color: #fff;
}
.info-tab-btn:hover { color: #fff; }

.info-tab-icon { font-size: 0.85rem; }

/* ── Sub-tabs ── */
.info-subtab-strip {
  display: flex;
  overflow-x: auto;
  background: var(--ua-bg, #F5F5F5);
  padding: 0 8px;
  gap: 2px;
  border-bottom: 1px solid var(--ua-divider, #E0E0E0);
  scrollbar-width: none;
  -ms-overflow-style: none;
}
.info-subtab-strip::-webkit-scrollbar { display: none; }

.info-subtab-btn {
  flex-shrink: 0;
  padding: 8px 12px;
  background: none;
  border: none;
  border-bottom: 2px solid transparent;
  color: var(--ua-text-secondary, #757575);
  font-family: 'Nunito', sans-serif;
  font-size: 0.75rem;
  font-weight: 600;
  cursor: pointer;
  white-space: nowrap;
  transition: color 0.15s, border-color 0.15s;
  display: flex;
  align-items: center;
  gap: 4px;
}
.info-subtab-btn.active {
  color: var(--ua-primary, #009BA4);
  border-bottom-color: var(--ua-primary, #009BA4);
}
.info-subtab-btn:hover { color: var(--ua-primary, #009BA4); }

/* ── Content ── */
.info-panel {
  display: flex;
  flex-direction: column;
}

.info-content {
  padding: 16px;
  font-size: 0.9rem;
  line-height: 1.65;
  color: var(--ua-text, #212121);
}

/* ── Empty state ── */
.info-empty {
  padding: 48px 24px;
  text-align: center;
  color: var(--ua-text-secondary, #757575);
}
.info-empty i {
  font-size: 2.5rem;
  margin-bottom: 16px;
  display: block;
  color: var(--ua-grey, #9E9E9E);
}
.info-empty p { font-size: 0.95rem; }

/* ── Editor.js rendered content resets ── */
.editorjs-content :deep(h1),
.editorjs-content :deep(h2),
.editorjs-content :deep(h3),
.editorjs-content :deep(h4) {
  font-weight: 700;
  color: var(--ua-primary, #009BA4);
  margin: 14px 0 6px;
  line-height: 1.3;
}
.editorjs-content :deep(h1) { font-size: 1.4rem; }
.editorjs-content :deep(h2) { font-size: 1.2rem; }
.editorjs-content :deep(h3) { font-size: 1.05rem; }
.editorjs-content :deep(h4) { font-size: 0.95rem; }

.editorjs-content :deep(p)  { margin: 0 0 10px; }

.editorjs-content :deep(ul),
.editorjs-content :deep(ol) {
  margin: 0 0 10px;
  padding-left: 20px;
}
.editorjs-content :deep(li) { margin-bottom: 4px; }

.editorjs-content :deep(blockquote) {
  border-left: 3px solid var(--ua-primary, #009BA4);
  margin: 12px 0;
  padding: 8px 14px;
  color: var(--ua-text-secondary, #757575);
  font-style: italic;
  background: var(--ua-bg, #F5F5F5);
  border-radius: 0 4px 4px 0;
}

.editorjs-content :deep(img) {
  max-width: 100%;
  border-radius: 6px;
  margin: 8px 0;
}

.editorjs-content :deep(table) {
  width: 100%;
  border-collapse: collapse;
  font-size: 0.82rem;
  margin: 10px 0;
}
.editorjs-content :deep(td),
.editorjs-content :deep(th) {
  border: 1px solid var(--ua-divider, #E0E0E0);
  padding: 6px 10px;
}
.editorjs-content :deep(th) {
  background: var(--ua-bg, #F5F5F5);
  font-weight: 700;
}

.editorjs-content :deep(hr) {
  border: none;
  border-top: 2px solid var(--ua-divider, #E0E0E0);
  margin: 16px 0;
}

.editorjs-content :deep(code) {
  background: var(--ua-bg, #F5F5F5);
  border-radius: 3px;
  padding: 1px 5px;
  font-size: 0.82rem;
  font-family: monospace;
}

.info-no-content {
  color: var(--ua-text-secondary, #757575);
  font-style: italic;
}
</style>
