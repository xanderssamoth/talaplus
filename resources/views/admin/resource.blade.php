@extends('layouts.admin')

@section('title', __('admin.'.$resource) !== 'admin.'.$resource ? __('admin.'.$resource) : ($config['title'] ?? str($resource)->replace('-', ' ')->title()))

@section('content')
@php
    $fieldLabels = collect($config['fields'] ?? [])->pluck('label', 'name')->all();
    $baseLabels = [
        'message' => 'Notification',
        'created_at' => 'Créé à',
        'updated_at' => 'Date de mise à jour',
        'is_shared' => 'Publier',
        'is_free' => 'Gratuit',
        'for_youth' => 'Pour les jeunes',
        'files_count' => 'Images',
    ];
    $detailBaseLabels = array_merge($baseLabels, [
        'created_at' => 'Date de création',
    ]);
    $tableLabels = array_merge($baseLabels, $fieldLabels, $config['table_labels'] ?? []);
    $detailLabels = array_merge($detailBaseLabels, $fieldLabels);
    $groupField = collect($config['fields'] ?? [])->firstWhere('name', $config['group_by'] ?? null);
    $groupLabels = array_merge(['money' => __('admin.money'), 'percentage' => __('admin.percentage')], $groupField['options'] ?? []);
    $fieldOptions = collect($config['fields'] ?? [])->mapWithKeys(fn ($field) => [$field['name'] => $field['options'] ?? []])->all();
@endphp
<div class="ajax-loader d-none" id="ajax-loader">
    <div class="ajax-loader-box">
        <div class="ajax-loader-image" aria-hidden="true">
            <span></span><span></span><span></span>
        </div>
        <div class="small fw-semibold mt-2">Traitement en cours...</div>
    </div>
</div>

<section class="section">
    <div class="row">
        <div class="{{ ($config['readonly'] ?? false) ? 'col-lg-12' : 'col-lg-8' }}">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="card-title">{{ __('admin.list') }}</h5>
                        <button class="btn btn-sm btn-outline-primary" id="refresh-table" type="button">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div id="resource-alert"></div>
                    <div class="row g-2 align-items-center mb-3">
                        <div class="{{ ($config['group_by'] ?? false) || ($config['role_filter'] ?? false) ? 'col-md-7' : 'col-12' }}">
                            <input class="form-control" id="table-search" type="search" placeholder="Rechercher...">
                        </div>
                        @if ($config['group_by'] ?? false)
                            <div class="col-md-5">
                                <select class="form-select" id="group-filter">
                                    <option value="">Tous les groupes</option>
                                    @foreach ($groupLabels as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                        @endif
                        @if ($config['role_filter'] ?? false)
                            <div class="col-md-5">
                                <select class="form-select" id="role-filter">
                                    <option value="">Tous les rôles</option>
                                    @foreach (($config['role_filter_options'] ?? []) as $value => $label)
                                        @if ($value !== '')
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </div>
                        @endif
                    </div>
                    @if ($config['social_feed'] ?? false)
                        <div class="vstack gap-3" id="notifications-feed">
                            <div class="text-center text-muted py-4">{{ __('admin.empty') }}</div>
                        </div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover data-table">
                            <thead>
                            <tr>
                                @foreach ($config['columns'] as $column)
                                    <th>{{ $tableLabels[$column] ?? str($column)->replace('_', ' ')->title() }}</th>
                                @endforeach
                                <th class="text-end">{{ __('admin.actions') }}</th>
                            </tr>
                            </thead>
                            <tbody id="resource-rows">
                            <tr><td colspan="{{ count($config['columns']) + 1 }}" class="text-center text-muted">{{ __('admin.empty') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    @endif
                </div>
            </div>
        </div>

        @unless ($config['readonly'] ?? false)
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-center justify-content-between">
                            <h5 class="card-title">{{ __('admin.form') }}</h5>
                            <button class="btn btn-sm btn-outline-secondary" id="new-item" type="button">
                                <i class="bi bi-plus-lg"></i> {{ __('admin.new') }}
                            </button>
                        </div>

                        <form id="resource-form" enctype="multipart/form-data">
                            @csrf
                            <input type="hidden" id="item-id">

                            @foreach ($config['fields'] as $field)
                                @php
                                    $name = $field['name'];
                                    $type = $field['type'];
                                    $required = !empty($field['required']) ? 'required' : '';
                                @endphp

                                @if (str_starts_with($type, 'translatable'))
                                    <div class="mb-3 translatable-field" data-field="{{ $name }}">
                                        <label class="form-label">{{ $field['label'] }}</label>
                                        <ul class="nav nav-tabs translatable-tabs" role="tablist">
                                            @foreach (['fr' => __('admin.french'), 'en' => __('admin.english'), 'ln' => __('admin.lingala')] as $locale => $label)
                                                <li class="nav-item" role="presentation">
                                                    <button class="nav-link {{ $loop->first ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#{{ $name }}-{{ $locale }}" type="button" role="tab">{{ strtoupper($locale) }}</button>
                                                </li>
                                            @endforeach
                                        </ul>
                                        <div class="tab-content border border-top-0 p-2">
                                            @foreach (['fr' => __('admin.french'), 'en' => __('admin.english'), 'ln' => __('admin.lingala')] as $locale => $label)
                                                <div class="tab-pane fade {{ $loop->first ? 'show active' : '' }}" id="{{ $name }}-{{ $locale }}" role="tabpanel">
                                                    <label class="form-label small">{{ $field['label'] }} ({{ strtoupper($locale) }})</label>
                                                    @if ($type === 'translatable-textarea')
                                                        <textarea class="form-control" name="{{ $name }}[{{ $locale }}]" rows="3" {{ $loop->first ? $required : '' }}></textarea>
                                                    @else
                                                        <input class="form-control" name="{{ $name }}[{{ $locale }}]" type="text" {{ $loop->first ? $required : '' }}>
                                                    @endif
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @elseif ($type === 'textarea')
                                    <div class="mb-3">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        <textarea class="form-control" id="{{ $name }}" name="{{ $name }}" rows="3" {{ $required }}></textarea>
                                    </div>
                                @elseif ($type === 'select')
                                    <div class="mb-3">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        <select class="form-select" id="{{ $name }}" name="{{ $name }}" {{ $required }}>
                                            @foreach ($field['options'] as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif ($type === 'checkbox')
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" id="{{ $name }}" name="{{ $name }}" type="checkbox" value="1">
                                        <label class="form-check-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                    </div>
                                @elseif ($type === 'file-multiple')
                                    <div class="mb-3">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        <input class="form-control" id="{{ $name }}" name="{{ $name }}[]" type="file" multiple accept="{{ $field['accept'] ?? '' }}">
                                    </div>
                                @else
                                    <div class="mb-3">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        <input class="form-control" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" step="{{ $field['step'] ?? '' }}" {{ $required }}>
                                    </div>
                                @endif
                            @endforeach

                            @if (($config['children'] ?? null) === 'pricing_descriptions')
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0">{{ __('admin.descriptions') }}</h6>
                                        <button class="btn btn-sm btn-outline-primary" id="add-description" type="button">
                                            <i class="bi bi-plus-lg"></i> {{ __('admin.add') }}
                                        </button>
                                    </div>
                                    <div class="vstack gap-3" id="descriptions-fields"></div>
                                </div>
                            @endif

                            @if (($config['children'] ?? null) === 'about_titles')
                                <div class="border rounded p-3 mb-3">
                                    <div class="d-flex align-items-center justify-content-between mb-2">
                                        <h6 class="mb-0">{{ __('admin.titles') }}</h6>
                                        <button class="btn btn-sm btn-outline-primary" id="add-title" type="button">
                                            <i class="bi bi-plus-lg"></i> {{ __('admin.add') }}
                                        </button>
                                    </div>
                                    <div class="vstack gap-3" id="titles-fields"></div>
                                </div>
                            @endif

                            <div class="d-flex gap-2">
                                <button class="btn btn-primary" type="submit"><i class="bi bi-save"></i> {{ __('admin.save') }}</button>
                                <button class="btn btn-outline-secondary" id="reset-form" type="button">{{ __('admin.reset') }}</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endunless
    </div>
</section>

<div class="modal fade" id="details-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">{{ __('admin.details') }}</h5>
                <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body" id="details-content"></div>
        </div>
    </div>
</div>

<template id="description-template">
    <div class="border rounded p-3 child-block" data-description-index="__INDEX__">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Description</strong>
            <button class="btn btn-sm btn-outline-danger remove-child" type="button"><i class="bi bi-trash"></i></button>
        </div>
        @foreach (['description_title' => 'Titre de la description', 'description_content' => 'Contenu de la description'] as $fieldName => $fieldLabel)
            <div class="mb-2">
                <label class="form-label">{{ $fieldLabel }}</label>
                <div class="row g-2">
                    @foreach (['fr' => 'FR', 'en' => 'EN', 'ln' => 'LN'] as $locale => $localeLabel)
                        <div class="col-md-4">
                            <input class="form-control" name="descriptions[__INDEX__][{{ $fieldName }}][{{ $locale }}]" placeholder="{{ $localeLabel }}">
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</template>

<template id="title-template">
    <div class="border rounded p-3 child-block" data-title-index="__TITLE__">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Titre legal</strong>
            <button class="btn btn-sm btn-outline-danger remove-child" type="button"><i class="bi bi-trash"></i></button>
        </div>
        <div class="mb-2">
            <label class="form-label">Titre</label>
            <div class="row g-2">
                @foreach (['fr' => 'FR', 'en' => 'EN', 'ln' => 'LN'] as $locale => $localeLabel)
                    <div class="col-md-4">
                        <input class="form-control" name="titles[__TITLE__][title][{{ $locale }}]" placeholder="{{ $localeLabel }}">
                    </div>
                @endforeach
            </div>
        </div>
        <div class="mb-2">
            <label class="form-label">Alias</label>
            <input class="form-control" name="titles[__TITLE__][alias]">
        </div>
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-semibold">{{ __('admin.contents') }}</span>
            <button class="btn btn-sm btn-outline-secondary add-content" type="button"><i class="bi bi-plus-lg"></i> {{ __('admin.add') }}</button>
        </div>
        <div class="vstack gap-2 contents-fields"></div>
    </div>
</template>

<template id="content-template">
    <div class="border rounded p-2 content-block" data-content-index="__CONTENT__">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Contenu</strong>
            <button class="btn btn-sm btn-outline-danger remove-child" type="button"><i class="bi bi-trash"></i></button>
        </div>
        @foreach (['subtitle' => 'Sous-titre', 'content' => 'Contenu'] as $fieldName => $fieldLabel)
            <div class="mb-2">
                <label class="form-label">{{ $fieldLabel }}</label>
                <div class="row g-2">
                    @foreach (['fr' => 'FR', 'en' => 'EN', 'ln' => 'LN'] as $locale => $localeLabel)
                        <div class="col-md-4">
                            <textarea class="form-control" name="titles[__TITLE__][contents][__CONTENT__][{{ $fieldName }}][{{ $locale }}]" rows="2" placeholder="{{ $localeLabel }}"></textarea>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="small fw-semibold">{{ __('admin.dashes') }}</span>
            <button class="btn btn-sm btn-outline-secondary add-dash" type="button"><i class="bi bi-plus-lg"></i> {{ __('admin.add') }}</button>
        </div>
        <div class="vstack gap-2 dashes-fields"></div>
    </div>
</template>

<template id="dash-template">
    <div class="border rounded p-2 dash-block" data-dash-index="__DASH__">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Tiret</strong>
            <button class="btn btn-sm btn-outline-danger remove-child" type="button"><i class="bi bi-trash"></i></button>
        </div>
        <div class="row g-2">
            @foreach (['fr' => 'FR', 'en' => 'EN', 'ln' => 'LN'] as $locale => $localeLabel)
                <div class="col-md-4">
                    <input class="form-control" name="titles[__TITLE__][contents][__CONTENT__][dashes][__DASH__][dash_content][{{ $locale }}]" placeholder="{{ $localeLabel }}">
                </div>
            @endforeach
        </div>
        <input class="form-control mt-2" name="titles[__TITLE__][contents][__CONTENT__][dashes][__DASH__][belongs_to]" type="number" placeholder="Depend du tiret ID">
    </div>
</template>
@endsection

@push('scripts')
<style>
    .ajax-loader {
        align-items: center;
        background: rgba(255, 255, 255, .72);
        inset: 0;
        justify-content: center;
        position: fixed;
        z-index: 2000;
    }

    .ajax-loader:not(.d-none) {
        display: flex;
    }

    .ajax-loader-box {
        background: #fff;
        border: 1px solid #e6edf7;
        border-radius: 8px;
        box-shadow: 0 12px 32px rgba(1, 41, 112, .16);
        padding: 18px 22px;
        text-align: center;
    }

    .ajax-loader-image {
        display: inline-flex;
        gap: 6px;
    }

    .ajax-loader-image span {
        animation: tala-loader .8s infinite ease-in-out alternate;
        background: #0d6efd;
        border-radius: 999px;
        display: block;
        height: 12px;
        width: 12px;
    }

    .ajax-loader-image span:nth-child(2) {
        animation-delay: .15s;
        background: #20c997;
    }

    .ajax-loader-image span:nth-child(3) {
        animation-delay: .3s;
        background: #ffc107;
    }

    .notification-card {
        border: 1px solid #e6edf7;
        border-left: 4px solid #adb5bd;
        border-radius: 8px;
        padding: 14px;
    }

    .notification-card.unread {
        background: #f6faff;
        border-left-color: #0d6efd;
    }

    .notification-card.read {
        background: #fff;
        opacity: .78;
    }

    .notification-dot {
        background: #0d6efd;
        border-radius: 999px;
        display: inline-block;
        height: 9px;
        width: 9px;
    }

    .category-icon-preview {
        align-items: center;
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        display: inline-flex;
        font-size: 1.25rem;
        height: 36px;
        justify-content: center;
        width: 36px;
    }

    .table-text-cell {
        max-width: 370px;
        overflow-wrap: anywhere;
        white-space: normal;
    }

    @keyframes tala-loader {
        from { transform: translateY(0); opacity: .55; }
        to { transform: translateY(-8px); opacity: 1; }
    }
</style>
<script>
    $(function () {
        const endpoints = {
            list: @json(route($resource.'.data')),
            store: @json(route($resource.'.store')),
            show: (id) => @json(url($resource)) + '/' + id,
            update: (id) => @json(url($resource)) + '/' + id,
            destroy: (id) => @json(url($resource)) + '/' + id,
            read: (id) => @json(url($resource)) + '/' + id + '/read',
            status: (id) => @json(url($resource)) + '/' + id + '/status',
        };
        const columns = @json($config['columns']);
        const groupBy = @json($config['group_by'] ?? null);
        const readonly = @json($config['readonly'] ?? false);
        const shareable = @json($config['shareable'] ?? false);
        const hasFiles = @json($config['has_files'] ?? false);
        const socialFeed = @json($config['social_feed'] ?? false);
        const statusEditable = @json($config['status_editable'] ?? false);
        const fieldOptions = @json($fieldOptions);
        const detailLabels = @json($detailLabels);
        let allItems = [];
        let descriptionIndex = 0;
        let titleIndex = 0;

        $.ajaxSetup({
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
        });

        $(document).ajaxStart(function () {
            $('#ajax-loader').removeClass('d-none');
        }).ajaxStop(function () {
            $('#ajax-loader').addClass('d-none');
        });

        function alertBox(message, type = 'success') {
            $('#resource-alert').html(`<div class="alert alert-${type} alert-dismissible fade show" role="alert">${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>`);
        }

        function display(value, column = '') {
            if (value === null || value === undefined || value === '') return '-';
            if (column.startsWith('is_') || column.startsWith('has_') || column === 'for_youth') {
                if (value === 1 || value === true || value === '1') return '{{ __('admin.yes') }}';
                if (value === 0 || value === false || value === '0') return '{{ __('admin.no') }}';
            }
            return $('<div>').text(String(value)).html();
        }

        function safeColor(value) {
            const color = String(value || '#6c757d').trim();
            const isHex = /^#([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/i.test(color);
            const isNamed = /^[a-zA-Z]+$/.test(color);
            const isRgb = /^rgba?\([\d\s.,%]+\)$/i.test(color);

            return isHex || isNamed || isRgb ? color : '#6c757d';
        }

        function textCellClass(value) {
            return typeof value === 'string' && value.length > 60 ? ' class="table-text-cell"' : '';
        }

        function normalized(value) {
            return String(value ?? '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        }

        function itemSearchText(item) {
            return normalized(Object.keys(item)
                .filter(key => !String(key).endsWith('_detail_display'))
                .map(key => {
                    const value = item[key + '_display'] ?? item[key];
                    return typeof value === 'object' && value !== null ? JSON.stringify(value) : value;
                })
                .join(' '));
        }

        function statusButtonClass(status) {
            return {
                created: 'btn-outline-secondary',
                activated: 'btn-outline-success',
                disabled: 'btn-outline-warning',
                blocked: 'btn-outline-danger',
                deleted: 'btn-outline-dark',
            }[status] || 'btn-outline-secondary';
        }

        function statusDropdown(item, value) {
            const current = item.status || '';
            const label = fieldOptions.status?.[current] || display(value, 'status');
            const options = Object.keys(fieldOptions.status || {}).map(status => `
                <li><button class="dropdown-item change-status ${status === current ? 'active' : ''}" data-id="${item.id}" data-status="${display(status)}" type="button">${display(fieldOptions.status[status])}</button></li>
            `).join('');

            return `<td><div class="dropdown">
                <button class="btn btn-sm dropdown-toggle ${statusButtonClass(current)}" data-bs-toggle="dropdown" type="button">${display(label)}</button>
                <ul class="dropdown-menu">${options}</ul>
            </div></td>`;
        }

        function rowHtml(item) {
            const cells = columns.map(column => {
                const value = item[column + '_display'] ?? item[column];
                if (column === 'status' && statusEditable) {
                    return statusDropdown(item, value);
                }
                if (column === 'is_shared' && shareable) {
                    const shared = item[column] === 1 || item[column] === true || item[column] === '1';
                    return `<td><button class="btn btn-sm ${shared ? 'btn-success' : 'btn-danger'} toggle-shared" data-id="${item.id}" type="button">${shared ? @json(__('admin.yes')) : @json(__('admin.no'))}</button></td>`;
                }
                if (column === 'message' && item.url) {
                    return `<td${textCellClass(value)}><a href="${item.url}" class="fw-semibold">${display(value, column)}</a></td>`;
                }
                if (column === 'icon' && item.icon_preview?.class) {
                    return `<td><span class="category-icon-preview" title="${display(item.icon)}"><i class="${display(item.icon_preview.class)}" style="color:${safeColor(item.icon_preview.color)}"></i></span></td>`;
                }

                return `<td${textCellClass(value)}>${display(value, column)}</td>`;
            }).join('');
            const writeActions = readonly ? '' : `
                <button class="btn btn-sm btn-outline-primary edit-item" data-id="${item.id}" type="button"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger delete-item" data-id="${item.id}" type="button"><i class="bi bi-trash"></i></button>`;
            const actions = `<td class="text-end">
                <button class="btn btn-sm btn-outline-secondary detail-item" data-id="${item.id}" type="button"><i class="bi bi-eye"></i></button>
                ${writeActions}
            </td>`;
            return `<tr>${cells}${actions}</tr>`;
        }

        function groupedRows(items) {
            if (!groupBy) return items.map(rowHtml).join('');
            const groups = {};
            items.forEach(item => {
                const key = item[groupBy] || '-';
                groups[key] = groups[key] || [];
                groups[key].push(item);
            });
            return Object.keys(groups).map(key => {
                const groupNames = @json($groupLabels);
                const title = groupNames[key] || key;
                return `<tr class="table-light"><th colspan="${columns.length + 1}">${title}</th></tr>` + groups[key].map(rowHtml).join('');
            }).join('');
        }

        function notificationHtml(item) {
            const read = item.is_read === 1 || item.is_read === true || item.is_read === '1';
            const status = read ? @json(__('admin.read')) : @json(__('admin.unread'));
            const message = display(item.message_display || item.type);
            const date = display(item.created_at_display || item.created_at);
            const link = item.url ? `<a class="stretched-link" href="${item.url}" target="_blank" rel="noopener"></a>` : '';
            const readButton = read ? '' : `<button class="btn btn-sm btn-outline-primary mark-read position-relative z-1" data-id="${item.id}" type="button">
                <i class="bi bi-check2-circle"></i> ${@json(__('admin.mark_read'))}
            </button>`;

            return `<div class="notification-card ${read ? 'read' : 'unread'} position-relative">
                <div class="d-flex align-items-start justify-content-between gap-3">
                    <div>
                        <div class="d-flex align-items-center gap-2 mb-1">
                            ${read ? '' : '<span class="notification-dot"></span>'}
                            <span class="badge ${read ? 'bg-secondary' : 'bg-primary'}">${status}</span>
                            <span class="text-muted small">${date}</span>
                        </div>
                        <div class="fw-semibold text-dark">${message}</div>
                    </div>
                    ${readButton}
                </div>
                ${link}
            </div>`;
        }

        function filteredItems() {
            const search = normalized($('#table-search').val());
            const group = $('#group-filter').val();
            const role = $('#role-filter').val();

            return allItems.filter(item => {
                const matchesSearch = !search || itemSearchText(item).includes(search);
                const matchesGroup = !group || String(item[groupBy] || '') === String(group);
                const matchesRole = !role || String(item.role_id || '') === String(role);

                return matchesSearch && matchesGroup && matchesRole;
            });
        }

        function renderRows() {
            const items = filteredItems();
            if (socialFeed) {
                $('#notifications-feed').html(items.length ? items.map(notificationHtml).join('') : `<div class="text-center text-muted py-4">{{ __('admin.empty') }}</div>`);
                return;
            }

            $('#resource-rows').html(items.length ? groupedRows(items) : `<tr><td colspan="${columns.length + 1}" class="text-center text-muted">{{ __('admin.empty') }}</td></tr>`);
        }

        function loadRows() {
            $.getJSON(endpoints.list, function (response) {
                allItems = response.items || [];
                renderRows();
            });
        }

        function resetForm() {
            $('#resource-form')[0]?.reset();
            $('#item-id').val('');
            $('#resource-form input[type=checkbox]').prop('checked', false);
            $('#descriptions-fields, #titles-fields').empty();
            descriptionIndex = 0;
            titleIndex = 0;
        }

        function parseTranslated(value) {
            if (!value) return {};
            if (typeof value === 'string') {
                try { return JSON.parse(value); } catch (e) { return {fr: value}; }
            }
            return value;
        }

        function addDescription(values = {}) {
            const html = $('#description-template').html().replaceAll('__INDEX__', descriptionIndex++);
            const $block = $(html);
            $('#descriptions-fields').append($block);
            fillTranslated($block, 'description_title', values.description_title);
            fillTranslated($block, 'description_content', values.description_content);
        }

        function addTitle(values = {}) {
            const currentTitleIndex = titleIndex++;
            const html = $('#title-template').html().replaceAll('__TITLE__', currentTitleIndex);
            const $block = $(html);
            $('#titles-fields').append($block);
            fillTranslated($block, 'title', values.title);
            $block.find(`[name="titles[${currentTitleIndex}][alias]"]`).val(values.alias || '');
            (values.contents || []).forEach(content => addContent($block, content));
        }

        function addContent($titleBlock, values = {}) {
            const titleIdx = $titleBlock.data('title-index');
            const contentIdx = $titleBlock.find('.content-block').length;
            const html = $('#content-template').html().replaceAll('__TITLE__', titleIdx).replaceAll('__CONTENT__', contentIdx);
            const $block = $(html);
            $titleBlock.find('.contents-fields').append($block);
            fillTranslated($block, 'subtitle', values.subtitle);
            fillTranslated($block, 'content', values.content);
            (values.dashes || []).forEach(dash => addDash($block, dash));
        }

        function addDash($contentBlock, values = {}) {
            const titleIdx = $contentBlock.closest('.child-block').data('title-index');
            const contentIdx = $contentBlock.data('content-index');
            const dashIdx = $contentBlock.find('.dash-block').length;
            const html = $('#dash-template').html().replaceAll('__TITLE__', titleIdx).replaceAll('__CONTENT__', contentIdx).replaceAll('__DASH__', dashIdx);
            const $block = $(html);
            $contentBlock.find('.dashes-fields').append($block);
            fillTranslated($block, 'dash_content', values.dash_content);
            $block.find(`[name$="[belongs_to]"]`).val(values.belongs_to || '');
        }

        function fillTranslated($scope, key, value) {
            const translated = parseTranslated(value);
            ['fr', 'en', 'ln'].forEach(locale => {
                $scope.find(`[name$="[${key}][${locale}]"]`).val(translated?.[locale] || '');
            });
        }

        function detailHtml(item) {
            const rows = Object.keys(item)
                .filter(key => !String(key).endsWith('_display'))
                .map(key => {
                    const label = detailLabels[key] || key.replaceAll('_', ' ');
                    let value = item[key + '_detail_display'] ?? item[key + '_display'] ?? item[key];
                    if (key === 'files' && Array.isArray(value)) {
                        value = value.length ? `<div class="row g-2">${value.map(file => `
                            <div class="col-md-4">
                                <a href="${file.file_url}" target="_blank" rel="noopener">
                                    <img src="${file.file_url}" class="img-fluid rounded border" alt="${display(file.file_name)}">
                                </a>
                            </div>
                        `).join('')}</div>` : '-';
                    } else if (typeof value === 'object' && value !== null) {
                        value = `<pre class="mb-0 small">${$('<div>').text(JSON.stringify(value, null, 2)).html()}</pre>`;
                    } else {
                        value = display(value, key);
                    }

                    return `<tr><th class="text-nowrap">${label}</th><td>${value}</td></tr>`;
                }).join('');

            return `<div class="table-responsive"><table class="table table-sm">${rows}</table></div>`;
        }

        $('#resource-form').on('submit', function (event) {
            event.preventDefault();
            const id = $('#item-id').val();
            let method = id ? 'PUT' : 'POST';
            const url = id ? endpoints.update(id) : endpoints.store;
            const ajaxOptions = {url, method};

            if (hasFiles) {
                const formData = new FormData(this);
                if (id) {
                    formData.append('_method', 'PUT');
                    method = 'POST';
                    ajaxOptions.method = method;
                }
                ajaxOptions.data = formData;
                ajaxOptions.processData = false;
                ajaxOptions.contentType = false;
            } else {
                ajaxOptions.data = $(this).serialize();
            }

            $.ajax(ajaxOptions)
                .done(function (response) {
                    alertBox(response.message || @json(__('admin.saved')));
                    resetForm();
                    loadRows();
                })
                .fail(function (xhr) {
                    alertBox(xhr.responseJSON?.message || xhr.statusText, 'danger');
                });
        });

        $(document).on('click', '.edit-item', function () {
            $.getJSON(endpoints.show($(this).data('id')), function (item) {
                resetForm();
                $('#item-id').val(item.id);
                Object.keys(item).forEach(function (key) {
                    const value = item[key];
                    const translated = parseTranslated(value);
                    if ($(`[name="${key}[fr]"]`).length) {
                        ['fr', 'en', 'ln'].forEach(locale => $(`[name="${key}[${locale}]"]`).val(translated?.[locale] || ''));
                    } else if ($(`[name="${key}"]`).is(':checkbox')) {
                        $(`[name="${key}"]`).prop('checked', value == 1);
                    } else if (!$(`[name="${key}"]`).is(':file')) {
                        $(`[name="${key}"]`).val(value);
                    }
                });
                (item.descriptions || []).forEach(addDescription);
                (item.titles || []).forEach(addTitle);
            });
        });

        $(document).on('click', '.detail-item', function () {
            $.getJSON(endpoints.show($(this).data('id')), function (item) {
                $('#details-content').html(detailHtml(item));
                new bootstrap.Modal('#details-modal').show();
            });
        });

        $(document).on('click', '.toggle-shared', function () {
            $.ajax({url: endpoints.show($(this).data('id')) + '/shared', method: 'PATCH'})
                .done(function (response) {
                    alertBox(response.message || @json(__('admin.saved')));
                    loadRows();
                })
                .fail(function (xhr) {
                    alertBox(xhr.responseJSON?.message || xhr.statusText, 'danger');
                });
        });

        $(document).on('click', '.mark-read', function () {
            $.ajax({url: endpoints.read($(this).data('id')), method: 'PATCH'})
                .done(function (response) {
                    alertBox(response.message || @json(__('admin.notification_read')));
                    loadRows();
                })
                .fail(function (xhr) {
                    alertBox(xhr.responseJSON?.message || xhr.statusText, 'danger');
                });
        });

        $(document).on('click', '.change-status', function () {
            $.ajax({
                url: endpoints.status($(this).data('id')),
                method: 'PATCH',
                data: {status: $(this).data('status')},
            })
                .done(function (response) {
                    alertBox(response.message || @json(__('admin.saved')));
                    loadRows();
                })
                .fail(function (xhr) {
                    alertBox(xhr.responseJSON?.message || xhr.statusText, 'danger');
                });
        });

        $(document).on('click', '.delete-item', function () {
            if (!confirm(@json(__('admin.confirm_delete')))) return;
            $.ajax({url: endpoints.destroy($(this).data('id')), method: 'DELETE'})
                .done(function (response) {
                    alertBox(response.message || @json(__('admin.deleted')));
                    loadRows();
                })
                .fail(function (xhr) {
                    alertBox(xhr.responseJSON?.message || xhr.statusText, 'danger');
                });
        });

        $('#refresh-table').on('click', loadRows);
        $('#table-search, #group-filter, #role-filter').on('input change', renderRows);
        $('#new-item, #reset-form').on('click', resetForm);
        $('#add-description').on('click', () => addDescription());
        $('#add-title').on('click', () => addTitle());
        $(document).on('click', '.add-content', function () { addContent($(this).closest('.child-block')); });
        $(document).on('click', '.add-dash', function () { addDash($(this).closest('.content-block')); });
        $(document).on('click', '.remove-child', function () { $(this).closest('.child-block, .content-block, .dash-block').remove(); });
        loadRows();
    });
</script>
@endpush
