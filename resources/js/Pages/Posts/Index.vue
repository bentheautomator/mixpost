<script setup>
import {onMounted, onUnmounted, ref, watch} from "vue";
import {Head} from '@inertiajs/vue3';
import {router, useForm} from "@inertiajs/vue3";
import emitter from "@/Services/emitter";
import useNotifications from "@/Composables/useNotifications";
import {cloneDeep, pickBy, throttle} from "lodash";
import useSelectable from "@/Composables/useSelectable";
import PageHeader from '@/Components/DataDisplay/PageHeader.vue';
import PostsFilter from '@/Components/Post/PostsFilter.vue';
import Tabs from "@/Components/Navigation/Tabs.vue"
import Tab from "@/Components/Navigation/Tab.vue"
import Panel from "@/Components/Surface/Panel.vue";
import Checkbox from "@/Components/Form/Checkbox.vue";
import Table from "@/Components/DataDisplay/Table.vue";
import TableRow from "@/Components/DataDisplay/TableRow.vue";
import TableCell from "@/Components/DataDisplay/TableCell.vue";
import SecondaryButton from "@/Components/Button/SecondaryButton.vue";
import PrimaryButton from "@/Components/Button/PrimaryButton.vue";
import PureDangerButton from "@/Components/Button/PureDangerButton.vue";
import DangerButton from "@/Components/Button/DangerButton.vue"
import Modal from "@/Components/Modal/Modal.vue";
import InputError from "@/Components/Form/InputError.vue";
import PostItem from "@/Components/Post/PostItem.vue";
import SelectableBar from "@/Components/DataDisplay/SelectableBar.vue";
import ConfirmationModal from "@/Components/Modal/ConfirmationModal.vue";
import Pagination from "@/Components/Navigation/Pagination.vue";
import NoResult from "@/Components/Util/NoResult.vue";
import TrashIcon from "@/Icons/Trash.vue";

const props = defineProps({
    filter: {
        type: Object,
        default: {}
    },
    posts: {
        type: Object,
    },
    has_failed_posts: {
        type: Boolean,
        default: false
    }
});

const filter = ref({
    keyword: props.filter.keyword,
    status: props.filter.status,
    tags: props.filter.tags,
    accounts: props.filter.accounts
})

const {
    selectedRecords,
    putPageRecords,
    toggleSelectRecordsOnPage,
    deselectRecord,
    deselectAllRecords
} = useSelectable();

const itemsId = () => {
    return props.posts.data.map(item => item.id);
}

onMounted(() => {
    putPageRecords(itemsId());

    emitter.on('postDelete', id => {
        deselectRecord(id);
    });
});

onUnmounted(() => {
    emitter.off('postDelete');
})

watch(() => cloneDeep(filter.value), throttle(() => {
    router.get(route('mixpost.posts.index'), pickBy(filter.value), {
        preserveState: true,
        only: ['posts', 'filter']
    });
}, 300))

watch(() => props.posts.data, () => {
    putPageRecords(itemsId());
})

const {notify} = useNotifications();
const confirmationDeletion = ref(false);

const importModal = ref(false);
const importForm = useForm({
    file: null
});

const onImportFileChange = (event) => {
    importForm.file = event.target.files[0] || null;
};

const submitImport = () => {
    importForm.post(route('mixpost.posts.import'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess(page) {
            importModal.value = false;
            importForm.reset();

            if (page.props.flash.error) {
                notify('error', page.props.flash.error);

                return;
            }

            notify('success', page.props.flash.success);

            router.reload({only: ['posts']});
        }
    });
};

const closeImportModal = () => {
    if (importForm.processing) {
        return;
    }

    importModal.value = false;
    importForm.reset();
    importForm.clearErrors();
};

const deletePosts = () => {
    router.delete(route('mixpost.posts.multipleDelete'), {
        data: {
            posts: selectedRecords.value,
            status: filter.value.status
        },
        onSuccess() {
            deselectAllRecords();
            notify('success', 'Selected posts deleted')
        },
        onFinish() {
            confirmationDeletion.value = false;
        }
    });
}
</script>
<template>
    <Head title="Posts"/>

    <div class="row-py mb-2xl">
        <PageHeader title="Posts">
            <PostsFilter v-model="filter" class="ml-2"/>
            <SecondaryButton @click="importModal = true" class="ml-2">Import CSV</SecondaryButton>
        </PageHeader>

        <div class="w-full row-px">
            <Tabs>
                <Tab @click="filter.status = null" :active="!$page.props.filter.status">All</Tab>
                <Tab @click="filter.status = 'draft'" :active="$page.props.filter.status === 'draft'">Drafts</Tab>
                <Tab @click="filter.status = 'scheduled'" :active="$page.props.filter.status === 'scheduled'">Scheduled</Tab>
                <Tab @click="filter.status = 'published'" :active="$page.props.filter.status === 'published'">Published</Tab>
                <template v-if="has_failed_posts">
                    <Tab @click="filter.status = 'failed'" :active="$page.props.filter.status === 'failed'" class="text-red-500">Failed</Tab>
                </template>
            </Tabs>
        </div>

        <div class="w-full row-px mt-lg">
            <SelectableBar :count="selectedRecords.length" @close="deselectAllRecords">
                <PureDangerButton @click="confirmationDeletion = true" v-tooltip="'Delete'">
                    <TrashIcon/>
                </PureDangerButton>
            </SelectableBar>

            <Panel :with-padding="false">
                <Table>
                    <template #head>
                        <TableRow>
                            <TableCell component="th" scope="col" class="w-10">
                                <Checkbox v-model:checked="toggleSelectRecordsOnPage" :disabled="!posts.meta.total"/>
                            </TableCell>
                            <TableCell component="th" scope="col" class="w-44">Status</TableCell>
                            <TableCell component="th" scope="col" class="pl-0! text-left">Content</TableCell>
                            <TableCell component="th" scope="col" class="w-48">Media</TableCell>
                            <TableCell component="th" scope="col">Labels</TableCell>
                            <TableCell component="th" scope="col">Accounts</TableCell>
                            <TableCell component="th" scope="col"/>
                        </TableRow>
                    </template>
                    <template #body>
                        <template v-for="item in posts.data" :key="item.id">
                            <PostItem :item="item" :filter="posts.filter"
                                      @onDelete="()=> {deselectRecord(item.id)}">
                                <template #checkbox>
                                    <Checkbox v-model:checked="selectedRecords" :value="item.id" number/>
                                </template>
                            </PostItem>
                        </template>
                    </template>
                </Table>

                <NoResult v-if="!posts.meta.total" class="py-md px-md">No posts found.</NoResult>
            </Panel>

            <div v-if="posts.meta.links.length > 3" class="mt-lg">
                <Pagination :meta="posts.meta" :links="posts.links"/>
            </div>
        </div>
    </div>

    <ConfirmationModal :show="confirmationDeletion" variant="danger" @close="confirmationDeletion = false">
        <template #header>
            Delete posts
        </template>
        <template #body>
            Are you sure you want to delete selected posts?
        </template>
        <template #footer>
            <SecondaryButton @click="confirmationDeletion = false" class="mr-xs">Cancel</SecondaryButton>
            <DangerButton @click="deletePosts">Delete</DangerButton>
        </template>
    </ConfirmationModal>

    <Modal :show="importModal" :closeable="true" @close="closeImportModal">
        <div class="p-lg">
            <div class="text-lg font-medium">Import posts from CSV</div>

            <p class="mt-xs text-stone-800">
                The file must have a header row with the columns
                <span class="font-mono text-sm">content</span>,
                <span class="font-mono text-sm">scheduled_at</span> and
                <span class="font-mono text-sm">accounts</span>
                (an optional <span class="font-mono text-sm">tags</span> column is supported).
                Use account/tag ids separated by <span class="font-mono text-sm">|</span>.
                Rows with a future <span class="font-mono text-sm">scheduled_at</span> are scheduled; the rest import as drafts.
            </p>

            <div class="mt-lg">
                <input type="file"
                       accept=".csv,text/csv,text/plain"
                       @change="onImportFileChange"
                       class="block w-full text-sm"/>
                <InputError :message="importForm.errors.file" class="mt-xs"/>
            </div>

            <div class="mt-lg flex justify-end">
                <SecondaryButton @click="closeImportModal" :disabled="importForm.processing" class="mr-xs">Cancel</SecondaryButton>
                <PrimaryButton @click="submitImport" :is-loading="importForm.processing"
                               :disabled="importForm.processing || !importForm.file">Import
                </PrimaryButton>
            </div>
        </div>
    </Modal>
</template>
