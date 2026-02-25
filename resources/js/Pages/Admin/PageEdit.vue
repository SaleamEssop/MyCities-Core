<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-edit mr-2"></i>Edit Page: {{ page.title }}
      </h1>
      <a :href="route('pages-list')" class="btn btn-secondary">
        <i class="fas fa-arrow-left mr-2"></i>Back to Pages
      </a>
    </div>

    <div v-if="flash.message" :class="['alert', flash.class || 'alert-info']">{{ flash.message }}</div>

    <form @submit.prevent="submit">
      <input type="hidden" name="page_id" :value="page.id">

      <div class="row">

        <!-- LEFT: Content -->
        <div class="col-lg-8">
          <div class="card shadow mb-4">
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-primary">Page Content</h6>
            </div>
            <div class="card-body">

              <div class="form-group">
                <label><strong>Page Title <span class="text-danger">*</span></strong></label>
                <input v-model="form.title" type="text" class="form-control form-control-lg" required
                       :class="{ 'is-invalid': form.errors.title }">
                <div class="invalid-feedback">{{ form.errors.title }}</div>
              </div>

              <div class="form-group">
                <label><strong>URL Slug</strong></label>
                <div class="input-group">
                  <div class="input-group-prepend"><span class="input-group-text">/</span></div>
                  <input v-model="form.slug" type="text" class="form-control">
                </div>
                <small class="text-muted">Current URL: <code>{{ page.url }}</code></small>
              </div>

              <div class="form-group">
                <label><strong>Page Content</strong></label>
                <EditorBlock
                  v-model="form.page_content"
                  :imageUploadUrl="imageUploadUrl"
                  :imageByUrlUrl="imageByUrlUrl"
                  :csrfToken="csrfToken"
                />
              </div>

              <hr>

              <div class="row">
                <div class="col-md-6">
                  <div class="form-group">
                    <label><strong>SEO Title</strong></label>
                    <input v-model="form.meta_title" type="text" class="form-control" placeholder="SEO title (optional)">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="form-group">
                    <label><strong>Icon Class</strong></label>
                    <input v-model="form.icon" type="text" class="form-control" placeholder="e.g., fas fa-home">
                    <small class="text-muted">FontAwesome icon class</small>
                  </div>
                </div>
              </div>

              <div class="form-group">
                <label><strong>SEO Description</strong></label>
                <textarea v-model="form.meta_description" class="form-control" rows="2"></textarea>
              </div>

            </div>
          </div>
        </div>

        <!-- RIGHT: Settings -->
        <div class="col-lg-4">
          <div class="card shadow mb-4">
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-primary">Page Settings</h6>
            </div>
            <div class="card-body">

              <!-- ── Page Hierarchy ───────────────────────────────────── -->
              <div class="form-group">
                <label><strong>Page Position</strong></label>

                <div
                  class="hier-option"
                  :class="{ selected: !form.parent_id }"
                  @click="form.parent_id = ''"
                >
                  <div class="hier-radio">
                    <div class="hier-dot" :class="{ active: !form.parent_id }"></div>
                  </div>
                  <div class="hier-body">
                    <div class="hier-title">
                      <i class="fas fa-minus mr-1 text-primary"></i>
                      Top-level tab
                    </div>
                    <div class="hier-preview">
                      <span class="tab-pill active">{{ form.title || 'This page' }}</span>
                    </div>
                    <small class="text-muted">Appears as its own tab in the app</small>
                  </div>
                </div>

                <div
                  class="hier-option mt-2"
                  :class="{ selected: !!form.parent_id }"
                  @click="(!form.parent_id && parentPages.length) && (form.parent_id = parentPages[0].id)"
                >
                  <div class="hier-radio">
                    <div class="hier-dot" :class="{ active: !!form.parent_id }"></div>
                  </div>
                  <div class="hier-body">
                    <div class="hier-title">
                      <i class="fas fa-level-down-alt fa-flip-horizontal mr-1 text-success"></i>
                      Child page — nested under a parent
                    </div>
                    <div v-if="form.parent_id" class="hier-preview">
                      <span class="tab-pill">{{ parentPages.find(p => p.id == form.parent_id)?.title || '…' }}</span>
                      <i class="fas fa-chevron-right mx-1 text-muted" style="font-size:0.7rem"></i>
                      <span class="tab-pill active">{{ form.title || 'This page' }}</span>
                    </div>
                    <small class="text-muted">Nested inside a parent tab as a sub-tab</small>

                    <div v-if="parentPages.length === 0" class="alert alert-warning mt-2 mb-0 py-1 px-2 small">
                      <i class="fas fa-exclamation-triangle mr-1"></i>
                      No parent pages available.
                    </div>
                    <div v-else class="mt-2">
                      <label class="small mb-1"><strong>Choose parent:</strong></label>
                      <select v-model="form.parent_id" class="form-control form-control-sm" @click.stop>
                        <option value="">-- Select a parent page --</option>
                        <option v-for="p in parentPages" :key="p.id" :value="p.id">{{ p.title }}</option>
                      </select>
                    </div>
                  </div>
                </div>

                <!-- Shows children count when this page has children -->
                <div v-if="page.children_count" class="alert alert-info mt-2 mb-0 py-2 px-3 small">
                  <i class="fas fa-sitemap mr-1"></i>
                  This page has <strong>{{ page.children_count }}</strong> child page(s) nested under it.
                </div>
              </div>

              <div class="form-group">
                <label><strong>Sort Order</strong></label>
                <input v-model.number="form.sort_order" type="number" class="form-control" min="0">
                <small class="text-muted">Lower numbers appear first</small>
              </div>

              <hr>

              <div class="form-group">
                <div class="custom-control custom-switch mb-2">
                  <input type="checkbox" id="is_active" v-model="form.is_active" class="custom-control-input">
                  <label class="custom-control-label" for="is_active">
                    <strong>Active</strong><br><small class="text-muted">Page is visible</small>
                  </label>
                </div>
              </div>

              <div class="form-group">
                <div class="custom-control custom-switch">
                  <input type="checkbox" id="show_in_nav" v-model="form.show_in_navigation" class="custom-control-input">
                  <label class="custom-control-label" for="show_in_nav">
                    <strong>Show in Navigation</strong><br><small class="text-muted">Display in app menu</small>
                  </label>
                </div>
              </div>

              <hr>

              <button type="submit" class="btn btn-primary btn-lg btn-block" :disabled="form.processing">
                <span v-if="form.processing"><i class="fas fa-spinner fa-spin mr-2"></i>Saving…</span>
                <span v-else><i class="fas fa-save mr-2"></i>Save Changes</span>
              </button>

              <hr>


            </div>
          </div>

          <!-- Children list for parent pages -->
          <div v-if="page.children && page.children.length" class="card shadow mb-4 border-left-success">
            <div class="card-header py-3">
              <h6 class="m-0 font-weight-bold text-success">
                <i class="fas fa-sitemap mr-2"></i>Child Pages ({{ page.children.length }})
              </h6>
            </div>
            <div class="card-body">
              <ul class="list-group list-group-flush">
                <li v-for="child in page.children" :key="child.id"
                    class="list-group-item d-flex justify-content-between align-items-center py-2">
                  <span>
                    <i v-if="child.icon" :class="child.icon" class="mr-2"></i>
                    {{ child.title }}
                  </span>
                  <a :href="`/admin/pages/edit/${child.id}`" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-edit"></i>
                  </a>
                </li>
              </ul>
              <a :href="`${route('pages-create')}?parent=${page.id}`" class="btn btn-success btn-sm btn-block mt-3">
                <i class="fas fa-plus mr-1"></i>Add Child Page
              </a>
            </div>
          </div>

        </div>
      </div>
    </form>
  </AdminLayout>
</template>

<script setup>
import { computed } from 'vue'
import { useForm, usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/Layouts/AdminLayout.vue'
import EditorBlock from '@/components/EditorBlock.vue'

const props = defineProps({
  page:           { type: Object, required: true },
  parentPages:    { type: Array,  default: () => [] },
  imageUploadUrl: { type: String, required: true },
  imageByUrlUrl:  { type: String, required: true },
  csrfToken:      { type: String, required: true },
})

const inertiaPage = usePage()
const flash       = computed(() => inertiaPage.props.flash ?? {})

const form = useForm({
  page_id:           props.page.id,
  title:             props.page.title             ?? '',
  slug:              props.page.slug              ?? '',
  page_type:         props.page.page_type         ?? 'single',
  parent_id:         props.page.parent_id         ?? '',
  page_content:      props.page.content           ?? '',
  icon:              props.page.icon              ?? '',
  sort_order:        props.page.sort_order        ?? 0,
  is_active:         props.page.is_active         ?? true,
  show_in_navigation: props.page.show_in_navigation ?? true,
  meta_title:        props.page.meta_title        ?? '',
  meta_description:  props.page.meta_description  ?? '',
})

function submit() {
  form.post(route('pages-update'), { preserveScroll: true })
}
</script>

<style scoped>
.hier-option {
  display: flex; gap: 12px; padding: 12px 14px;
  border: 2px solid #e3e6f0; border-radius: 8px;
  cursor: pointer; transition: border-color 0.15s, background 0.15s; background: #fff;
}
.hier-option:hover  { border-color: #b0b8d0; }
.hier-option.selected { border-color: #4e73df; background: #f0f4ff; }
.hier-radio { padding-top: 3px; flex-shrink: 0; }
.hier-dot {
  width: 16px; height: 16px; border-radius: 50%;
  border: 2px solid #b0b8d0; background: #fff; transition: all 0.15s;
}
.hier-dot.active { border-color: #4e73df; background: #4e73df; }
.hier-body { flex: 1; }
.hier-title { font-weight: 600; font-size: 0.9rem; margin-bottom: 6px; }
.hier-preview { display: flex; align-items: center; margin-bottom: 4px; flex-wrap: wrap; gap: 4px; }
.tab-pill {
  background: #e9ecef; color: #555;
  border-radius: 20px; padding: 2px 10px;
  font-size: 0.75rem; font-weight: 600;
}
.tab-pill.active { background: #009BA4; color: #fff; }
</style>
