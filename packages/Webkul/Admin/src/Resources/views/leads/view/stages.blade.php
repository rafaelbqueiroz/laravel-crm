<!-- Stages Navigation -->
{!! view_render_event('admin.leads.view.stages.before', ['lead' => $lead]) !!}

<!-- Stages Vue Component -->
<v-lead-stages>
    <x-admin::shimmer.leads.view.stages :count="$lead->pipeline->stages->count() - 1" />
</v-lead-stages>

{!! view_render_event('admin.leads.view.stages.after', ['lead' => $lead]) !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-lead-stages-template">
        <!-- Stages Container -->
        <div
            class="flex"
            :class="{'opacity-50 pointer-events-none': isUpdating}"
        >
            <!-- Stages Item -->
            <template v-for="stage in stages">
                <div
                    class="stage relative flex h-7 min-w-24 cursor-pointer items-center justify-center bg-white pl-7 pr-4 first:rounded-l-lg dark:bg-gray-900"
                    :class="{
                        '!bg-green-500 text-white dark:text-gray-900 after:bg-green-500': currentStage.sort_order >= stage.sort_order,
                        '!bg-red-500 text-white dark:text-gray-900 after:bg-red-500': currentStage.code == 'lost',
                    }"
                    v-if="! ['won', 'lost'].includes(stage.code)"
                    @click="update(stage)"
                >
                    <span class="z-20 whitespace-nowrap text-sm font-medium dark:text-white">
                        @{{ stage.name }}
                    </span>
                </div>
            </template>

            <!-- Won/Lost Stage Item -->
            <x-admin::dropdown position="bottom-right">
                <x-slot:toggle>
                    <div
                        class="relative flex h-7 min-w-24 cursor-pointer items-center justify-center rounded-r-lg bg-white pl-7 pr-4 dark:bg-gray-900"
                        :class="{
                            '!bg-green-500 text-white dark:text-gray-900 after:bg-green-500': ['won', 'lost'].includes(currentStage.code) && currentStage.code == 'won',
                            '!bg-red-500 text-white dark:text-gray-900 after:bg-red-500': ['won', 'lost'].includes(currentStage.code) && currentStage.code == 'lost',
                        }"
                    >
                        <span class="z-20 whitespace-nowrap text-sm font-medium dark:text-white">
                            {{ __('admin::app.leads.view.stages.won-lost') }}
                        </span>

                        <span class="icon-down-arrow text-2xl dark:text-gray-900"></span>
                    </div>
                </x-slot>

                <x-slot:menu>
                    <x-admin::dropdown.menu.item
                        @click="openModal(this.stages.find(stage => stage.code == 'won'))"
                    >
                        @lang('admin::app.leads.view.stages.won')
                    </x-admin::dropdown.menu.item>

                    <x-admin::dropdown.menu.item
                        @click="openModal(this.stages.find(stage => stage.code == 'lost'))"
                    >
                        @lang('admin::app.leads.view.stages.lost')
                    </x-admin::dropdown.menu.item>
                </x-slot>
            </x-admin::dropdown>

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="stageUpdateForm"
            >
                <form @submit="handleSubmit($event, handleFormSubmit)">
                    <x-admin::modal ref="stageUpdateModal">
                        <x-slot:header>
                            <h3 class="text-base font-semibold dark:text-white">
                                @lang('admin::app.leads.view.stages.need-more-info')
                            </h3>
                        </x-slot>

                        <x-slot:content>
                            <!-- Won Value -->
                            <template v-if="nextStage.code == 'won'">
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.view.stages.won-value')
                                    </x-admin::form.control-group.label>
                                    <x-admin::form.control-group.control
                                        type="price"
                                        name="lead_value"
                                        :value="$lead->lead_value"
                                        v-model="nextStage.lead_value"
                                    />
                                </x-admin::form.control-group>
                            </template>

                            <!-- Lost Reason -->
                            <template v-else>
                                <x-admin::form.control-group>
                                    <x-admin::form.control-group.label>
                                        @lang('admin::app.leads.view.stages.lost-reason')
                                    </x-admin::form.control-group.label>
                                    <x-admin::form.control-group.control
                                        type="textarea"
                                        name="lost_reason"
                                        v-model="nextStage.lost_reason"
                                    />
                                </x-admin::form.control-group>
                            </template>

                            <!-- Closed At -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.leads.view.stages.closed-at')
                                </x-admin::form.control-group.label>
                                <x-admin::form.control-group.control
                                    type="date"
                                    name="closed_at"
                                    v-model="nextStage.closed_at"
                                />
                            </x-admin::form.control-group>
                        </x-slot>

                        <x-slot:footer>
                            <button
                                type="submit"
                                class="primary-button"
                            >
                                @lang('admin::app.leads.view.stages.save-btn')
                            </button>
                        </x-slot>
                    </x-admin::modal>
                </form>
            </x-admin::form>
        </div>
    </script>

    <script type="module">
        app.component('v-lead-stages', {
            template: '#v-lead-stages-template',

            data() {
                return {
                    isUpdating: false,
                    
                    currentStage: @json($lead->stage),

                    nextStage: null,

                    stages: @json($lead->pipeline->stages),
                }
            },

            methods: {
                openModal(stage) {
                    this.nextStage = stage;

                    this.$refs.stageUpdateModal.open();
                },

                handleFormSubmit(event) {
                    let params = {
                        'lead_pipeline_stage_id': this.nextStage.id
                    };
                    
                    if (this.nextStage.code == 'won') {
                        params.lead_value = this.nextStage.lead_value;

                        params.closed_at = this.nextStage.closed_at;
                    } else if (this.nextStage.code == 'lost') {
                        params.lost_reason = this.nextStage.lost_reason;
                        
                        params.closed_at = this.nextStage.closed_at;
                    }

                    this.update(this.nextStage, params);
                },

                update(stage, params = null) {
                    if (this.currentStage.code == stage.code) {
                        return;
                    }

                    this.$refs.stageUpdateModal.close();

                    this.isUpdating = true;

                    let self = this;
                    
                    this.$axios.put("{{ route('admin.leads.update', $lead->id) }}", params ?? {
                        'lead_pipeline_stage_id': stage.id
                    })
                        .then (function(response) {
                            self.isUpdating = false;

                            self.currentStage = stage;
                            
                            self.$emitter.emit('add-flash', { type: 'success', message: response.data.message });
                        })
                        .catch (function (error) {
                            self.isUpdating = false;

                            self.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });
                        });
                },
            }
        });
    </script>
@endPushOnce