<template>
  <AdminLayout>
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
      <h1 class="h3 mb-0 text-gray-800">
        <i class="fas fa-file-alt mr-2"></i>Pages
      </h1>
      <a :href="route('pages-create')" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm">
        <i class="fas fa-plus fa-sm text-white-50"></i> Add New Page
      </a>
    </div>

    <div v-if="flash.message" :class="['alert', flash.class || 'alert-info']">{{ flash.message }}</div>

    <div class="card shadow mb-4">
      <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">All Pages ({{ pages.length }})</h6>
        <small class="text-muted">Pages appear in the Info tab of the user app</small>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-bordered" width="100%">
            <thead>
              <tr>
                <th>Title</th>
                <th>Slug</th>
                <th>Type</th>
                <th>Status</th>
                <th class="text-center">Order</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="entry in tree" :key="entry.id">
                <!-- Parent row -->
                <tr class="row-parent">
                  <td>
                    <i v-if="entry.icon" :class="entry.icon" class="mr-1 text-muted"></i>
                    <strong>{{ entry.title }}</strong>
                    <span v-if="entry.children.length" class="badge badge-light border ml-1" style="font-size:0.7rem;">
                      {{ entry.children.length }} child{{ entry.children.length !== 1 ? 'ren' : '' }}
                    </span>
                  </td>
                  <td><code>/{{ entry.slug }}</code></td>
                  <td>
                    <span class="badge" :class="entry.page_type === 'parent' ? 'badge-success' : 'badge-primary'">
                      {{ entry.page_type === 'parent' ? 'Menu Group' : 'Top-level Tab' }}
                    </span>
                  </td>
                  <td>
                    <span class="badge" :class="entry.is_active ? 'badge-success' : 'badge-secondary'">
                      {{ entry.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="text-center">{{ entry.sort_order }}</td>
                  <td>
                    <a :href="route('pages-edit', entry.id)" class="btn btn-sm btn-primary mr-1">
                      <i class="fas fa-edit"></i> Edit
                    </a>
                    <a :href="route('pages-preview', entry.id)" class="btn btn-sm btn-info mr-1" target="_blank">
                      <i class="fas fa-eye"></i>
                    </a>
                    <button @click="deletePage(entry.id)" class="btn btn-sm btn-danger">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
                <!-- Child rows (immediately after their parent) -->
                <tr v-for="child in entry.children" :key="child.id" class="row-child">
                  <td>
                    <span class="child-indent">
                      <span class="child-connector"></span>
                      <i v-if="child.icon" :class="child.icon" class="mr-1 text-muted"></i>
                      {{ child.title }}
                    </span>
                  </td>
                  <td><code>/{{ child.slug }}</code></td>
                  <td>
                    <span class="badge badge-secondary">Child Page</span>
                  </td>
                  <td>
                    <span class="badge" :class="child.is_active ? 'badge-success' : 'badge-secondary'">
                      {{ child.is_active ? 'Active' : 'Inactive' }}
                    </span>
                  </td>
                  <td class="text-center">{{ child.sort_order }}</td>
                  <td>
                    <a :href="route('pages-edit', child.id)" class="btn btn-sm btn-primary mr-1">
                      <i class="fas fa-edit"></i> Edit
                    </a>
                    <a :href="route('pages-preview', child.id)" class="btn btn-sm btn-info mr-1" target="_blank">
                      <i class="fas fa-eye"></i>
                    </a>
                    <button @click="deletePage(child.id)" class="btn btn-sm btn-danger">
                      <i class="fas fa-trash"></i>
                    </button>
                  </td>
                </tr>
              </template>
              <tr v-if="!pages.length">
                <td colspan="6" class="text-center py-4 text-muted">
                  <i class="fas fa-file-alt fa-2x mb-2 d-block"></i>
                  No pages yet. <a :href="route('pages-create')">Create your first page</a>.
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </AdminLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { route } from 'ziggy-js'
import AdminLayout from '@/Layouts/AdminLayout.vue'

const props = defineProps({ pages: { type: Array, default: () => [] } })
const pages = ref(props.pages || [])

const inertiaPage = usePage()
const flash = computed(() => inertiaPage.props.flash ?? {})

// Build a tree: top-level pages ordered by sort_order, each with their
// children (also ordered by sort_order) attached immediately below.
const tree = computed(() => {
  const all = pages.value
  const byId = Object.fromEntries(all.map(p => [p.id, { ...p, children: [] }]))

  // Attach children to their parent
  all.forEach(p => {
    if (p.parent_id && byId[p.parent_id]) {
      byId[p.parent_id].children.push(byId[p.id])
    }
  })

  // Sort children by sort_order
  Object.values(byId).forEach(p => p.children.sort((a, b) => a.sort_order - b.sort_order))

  // Return only top-level nodes, sorted by sort_order
  return Object.values(byId)
    .filter(p => !p.parent_id)
    .sort((a, b) => a.sort_order - b.sort_order)
})

const deletePage = async (id) => {
  if (!confirm('Delete this page? This cannot be undone.')) return
  await fetch(`/admin/pages/delete/${id}`, {
    method: 'GET',
    headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content },
  })
  pages.value = pages.value.filter(p => p.id !== id)
}
</script>

<style scoped>
.table { width: 100%; border-collapse: collapse; }
.table thead th {
  padding: 0.75rem;
  vertical-align: top;
  border-bottom: 2px solid #e3e6f0;
  background-color: #f8f9fc;
}
.table tbody td { padding: 0.75rem; vertical-align: middle; border-top: 1px solid #e3e6f0; }

/* Parent rows — subtle highlight */
.row-parent td { background-color: #f8f9fc; }
.row-parent td:first-child { border-left: 3px solid #4e73df; }

/* Child rows — indented, lighter background */
.row-child td { background-color: #fff; }
.row-child td:first-child { border-left: 3px solid #1cc88a; }

/* Tree connector for child rows */
.child-indent {
  display: inline-flex;
  align-items: center;
  padding-left: 1.5rem;
  color: #5a5c69;
}
.child-connector {
  display: inline-block;
  width: 14px;
  height: 14px;
  margin-right: 6px;
  border-left: 2px solid #b0b8d0;
  border-bottom: 2px solid #b0b8d0;
  border-radius: 0 0 0 4px;
  flex-shrink: 0;
}

.btn { padding: 0.25rem 0.75rem; font-size: 0.875rem; border-radius: 0.35rem; margin-right: 0.25rem; }
.btn-primary { background-color: #4e73df; color: #fff; }
.btn-info    { background-color: #36b9cc; color: #fff; }
.btn-danger  { background-color: #e74a3b; color: #fff; }
.card { border-radius: 0.35rem; box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15); }
.card-header { background-color: #f8f9fc; border-bottom: 1px solid #e3e6f0; }
</style>
