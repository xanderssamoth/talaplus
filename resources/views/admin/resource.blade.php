@extends('layouts.admin')

@section('title', __('admin.'.$resource) !== 'admin.'.$resource ? __('admin.'.$resource) : ($config['title'] ?? str($resource)->replace('-', ' ')->title()))

@if ($config['avatar_cropper'] ?? false)
    @push('styles')
        <link href="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.css" rel="stylesheet">
    @endpush
@endif

@section('content')
@php
    $listColumnClass = ($config['readonly'] ?? false) ? 'col-lg-12' : (($config['wide_form'] ?? false) ? 'col-lg-6' : 'col-lg-8');
    $formColumnClass = ($config['wide_form'] ?? false) ? 'col-lg-6' : 'col-lg-4';
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
    $groupLabels = $groupField['options'] ?? [];
    $fieldOptions = collect($config['fields'] ?? [])->mapWithKeys(fn ($field) => [$field['name'] => $field['options'] ?? []])->all();
    $hasFileUploads = ($config['has_files'] ?? false) || collect($config['fields'] ?? [])->contains(fn ($field) => ($field['type'] ?? null) === 'file-url');
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
        <div class="{{ $listColumnClass }}">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="card-title">{{ __('admin.list') }}</h5>
                        <button class="btn btn-sm btn-outline-primary" id="refresh-table" type="button">
                            <i class="bi bi-arrow-clockwise"></i>
                        </button>
                    </div>
                    <div class="resource-alert-fixed" id="resource-alert"></div>
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
                        <div id="resource-pagination"></div>
                    @else
                    <div class="table-responsive">
                        <table class="table table-hover data-table">
                            <thead>
                            <tr>
                                @foreach ($config['columns'] as $column)
                                    <th>{{ $tableLabels[$column] ?? str($column)->replace('_', ' ')->title() }}</th>
                                @endforeach
                                <th class="text-end"></th>
                            </tr>
                            </thead>
                            <tbody id="resource-rows">
                            <tr><td colspan="{{ count($config['columns']) + 1 }}" class="text-center text-muted">{{ __('admin.empty') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="resource-pagination"></div>
                    @endif
                </div>
            </div>
        </div>

        @unless ($config['readonly'] ?? false)
            <div class="{{ $formColumnClass }}">
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

                            @if ($config['user_modes'] ?? false)
                                <div class="btn-group w-100 mb-3" role="group" aria-label="Type d’utilisateur">
                                    <button class="btn btn-primary user-mode-switch active" data-user-mode="user" type="button">Ajouter utilisateur</button>
                                    <button class="btn btn-outline-primary user-mode-switch" data-user-mode="partner" type="button">Ajouter partenaire</button>
                                </div>
                                <input id="partner_role_id" name="role_id" type="hidden" value="{{ $config['partner_role_id'] ?? '' }}" disabled>
                            @endif

                            @foreach ($config['fields'] as $field)
                                @php
                                    $name = $field['name'];
                                    $type = $field['type'];
                                    $required = !empty($field['required']) ? 'required' : '';
                                @endphp

                                @if ($type === 'hidden')
                                    <input id="{{ $name }}" name="{{ $name }}" type="hidden" value="{{ $field['value'] ?? '' }}">
                                @elseif (str_starts_with($type, 'translatable'))
                                    <div class="mb-3 translatable-field" data-field="{{ $name }}" data-field-wrapper="{{ $name }}">
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
                                    <div class="mb-3" data-field-wrapper="{{ $name }}">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        <textarea class="form-control" id="{{ $name }}" name="{{ $name }}" rows="3" {{ $required }}></textarea>
                                    </div>
                                @elseif ($type === 'select')
                                    <div class="mb-3" data-field-wrapper="{{ $name }}">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        <select class="form-select" id="{{ $name }}" name="{{ $name }}" {{ $required }}>
                                            @foreach ($field['options'] as $value => $label)
                                                <option value="{{ $value }}" @foreach (($field['option_attrs'][$value] ?? []) as $attribute => $attributeValue) {{ $attribute }}="{{ $attributeValue }}" @endforeach>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif ($type === 'checkbox')
                                    <div class="form-check form-switch mb-3" data-field-wrapper="{{ $name }}">
                                        <input class="form-check-input" id="{{ $name }}" name="{{ $name }}" type="checkbox" value="1">
                                        <label class="form-check-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                    </div>
                                @elseif ($type === 'file-multiple')
                                    <div class="mb-3" data-field-wrapper="{{ $name }}">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        <input class="form-control" id="{{ $name }}" name="{{ $name }}[]" type="file" multiple accept="{{ $field['accept'] ?? '' }}">
                                    </div>
                                @elseif ($type === 'file-url')
                                    <div class="mb-3" data-field-wrapper="{{ $name }}">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        <input class="form-control file-url-input" id="{{ $name }}" name="{{ $name }}" type="file" accept="{{ $field['accept'] ?? '' }}" data-required-on-create="{{ !empty($field['required']) ? '1' : '0' }}" {{ !empty($field['required']) ? 'required' : '' }}>
                                        <div class="file-preview mt-2 d-none" data-preview-for="{{ $name }}"></div>
                                    </div>
                                @else
                                    <div class="mb-3" data-field-wrapper="{{ $name }}">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        @if ($type === 'password')
                                            <div class="input-group">
                                                <input class="form-control" id="{{ $name }}" name="{{ $name }}" type="password" step="{{ $field['step'] ?? '' }}" {{ $required }}>
                                                <button class="btn btn-outline-secondary toggle-password" data-target="{{ $name }}" type="button" aria-label="Afficher ou cacher le mot de passe">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                            </div>
                                        @else
                                            <input class="form-control" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" step="{{ $field['step'] ?? '' }}" {{ $required }}>
                                        @endif
                                    </div>
                                @endif
                            @endforeach

                            @if ($config['avatar_cropper'] ?? false)
                                <div class="border rounded p-3 mb-3">
                                    <label class="form-label">Photo de profil</label>
                                    <input id="avatar_base64" name="avatar_base64" type="hidden">
                                    <div class="d-flex align-items-center gap-3">
                                        <img class="rounded-circle border object-fit-cover" id="admin-user-avatar-preview" src="" alt="Aperçu avatar" width="72" height="72">
                                        <div>
                                            <input class="form-control form-control-sm" id="admin-user-avatar-input" type="file" accept="image/*">
                                            <div class="form-text">Recadrez l’image avant d’enregistrer.</div>
                                        </div>
                                    </div>
                                </div>
                            @endif

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

@if ($config['avatar_cropper'] ?? false)
    <div class="modal fade" id="admin-user-avatar-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Recadrer la photo</h5>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <div class="avatar-crop-frame border rounded bg-light p-2">
                        <img class="img-fluid d-block mx-auto" id="admin-user-avatar-crop-image" alt="Photo à recadrer">
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Annuler</button>
                    <button class="btn btn-primary" id="admin-user-avatar-crop-save" type="button">Utiliser cette photo</button>
                </div>
            </div>
        </div>
    </div>
@endif

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
                        <div class="col-12">
                            @if ($fieldName === 'description_content')
                                <textarea class="form-control" name="descriptions[__INDEX__][{{ $fieldName }}][{{ $locale }}]" rows="3" placeholder="{{ $localeLabel }}"></textarea>
                            @else
                                <input class="form-control" name="descriptions[__INDEX__][{{ $fieldName }}][{{ $locale }}]" placeholder="{{ $localeLabel }}">
                            @endif
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
                    <div class="col-12">
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
                        <div class="col-12">
                            @if ($fieldName === 'subtitle')
                                <input class="form-control" name="titles[__TITLE__][contents][__CONTENT__][{{ $fieldName }}][{{ $locale }}]" placeholder="{{ $localeLabel }}">
                            @else
                                <textarea class="form-control" name="titles[__TITLE__][contents][__CONTENT__][{{ $fieldName }}][{{ $locale }}]" rows="3" placeholder="{{ $localeLabel }}"></textarea>
                            @endif
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
                <div class="col-12">
                    <input class="form-control" name="titles[__TITLE__][contents][__CONTENT__][dashes][__DASH__][dash_content][{{ $locale }}]" placeholder="{{ $localeLabel }}">
                </div>
            @endforeach
        </div>
        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="small fw-semibold">Sous-tirets</span>
            <button class="btn btn-sm btn-outline-secondary add-sub-dash" type="button"><i class="bi bi-plus-lg"></i> Sous-tiret</button>
        </div>
        <div class="vstack gap-2 mt-2 sub-dashes-fields"></div>
    </div>
</template>

<template id="sub-dash-template">
    <div class="border rounded p-2 sub-dash-block" data-sub-dash-index="__SUBDASH__">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <strong>Sous-tiret</strong>
            <button class="btn btn-sm btn-outline-danger remove-child" type="button"><i class="bi bi-trash"></i></button>
        </div>
        <div class="row g-2">
            @foreach (['fr' => 'FR', 'en' => 'EN', 'ln' => 'LN'] as $locale => $localeLabel)
                <div class="col-12">
                    <input class="form-control" name="titles[__TITLE__][contents][__CONTENT__][dashes][__DASH__][sub_dashes][__SUBDASH__][dash_content][{{ $locale }}]" placeholder="{{ $localeLabel }}">
                </div>
            @endforeach
        </div>
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

    .resource-alert-fixed {
        left: 50%;
        max-width: 500px;
        position: fixed;
        top: 18px;
        transform: translateX(-50%);
        width: calc(100% - 32px);
        z-index: 2050;
    }

    .resource-alert-fixed .alert {
        box-shadow: 0 12px 32px rgba(1, 41, 112, .18);
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

    .file-preview-box {
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 10px;
    }

    .file-preview-box video,
    .file-preview-box img {
        max-height: 180px;
        object-fit: cover;
        width: 100%;
    }

    .avatar-crop-frame {
        max-height: min(62vh, 460px);
        overflow: hidden;
    }

    .avatar-crop-frame img {
        max-width: 100%;
    }

    @keyframes tala-loader {
        from { transform: translateY(0); opacity: .55; }
        to { transform: translateY(-8px); opacity: 1; }
    }
</style>
@if ($config['avatar_cropper'] ?? false)
    <script src="https://cdn.jsdelivr.net/npm/cropperjs@1.6.2/dist/cropper.min.js"></script>
@endif
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
        const hasFiles = @json($hasFileUploads);
        const socialFeed = @json($config['social_feed'] ?? false);
        const statusEditable = @json($config['status_editable'] ?? false);
        const fieldOptions = @json($fieldOptions);
        const detailLabels = @json($detailLabels);
        const userModes = @json($config['user_modes'] ?? null);
        const partnerRoleId = @json((string) ($config['partner_role_id'] ?? ''));
        const hasAvatarCropper = @json($config['avatar_cropper'] ?? false);
        const resourceName = @json($resource);
        const perPage = 20;
        let allItems = [];
        let currentPage = 1;
        let descriptionIndex = 0;
        let titleIndex = 0;
        let avatarCropper = null;
        let avatarModal = null;

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
                    return `<td>
                        <div class="d-flex align-items-center gap-2">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input toggle-shared" data-id="${item.id}" type="checkbox" ${shared ? 'checked' : ''}>
                            </div>
                            <span class="fw-semibold ${shared ? 'text-success' : 'text-danger'}">${shared ? @json(__('admin.yes')) : @json(__('admin.no'))}</span>
                        </div>
                    </td>`;
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
                <li><button class="dropdown-item edit-item" data-id="${item.id}" type="button"><i class="bi bi-pencil me-2"></i>Modifier</button></li>
                <li><button class="dropdown-item text-danger delete-item" data-id="${item.id}" type="button"><i class="bi bi-trash me-2"></i>Supprimer</button></li>`;
            const actions = `<td class="text-end">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" type="button" aria-label="Actions">
                        <i class="bi bi-three-dots-vertical"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><button class="dropdown-item detail-item" data-id="${item.id}" type="button"><i class="bi bi-eye me-2"></i>Voir</button></li>
                        ${writeActions}
                    </ul>
                </div>
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

        function pagedItems(items) {
            const totalPages = Math.max(1, Math.ceil(items.length / perPage));
            currentPage = Math.min(Math.max(currentPage, 1), totalPages);
            const start = (currentPage - 1) * perPage;

            return items.slice(start, start + perPage);
        }

        function renderPagination(totalItems) {
            const totalPages = Math.ceil(totalItems / perPage);
            if (totalPages <= 1) {
                $('#resource-pagination').empty();
                return;
            }

            const pageButtons = Array.from({length: totalPages}, (_, index) => index + 1)
                .filter(page => page === 1 || page === totalPages || Math.abs(page - currentPage) <= 2)
                .map((page, index, pages) => {
                    const gap = index > 0 && page - pages[index - 1] > 1 ? '<li class="page-item disabled"><span class="page-link">...</span></li>' : '';
                    return `${gap}<li class="page-item ${page === currentPage ? 'active' : ''}">
                        <button class="page-link pagination-page" data-page="${page}" type="button">${page}</button>
                    </li>`;
                })
                .join('');

            $('#resource-pagination').html(`<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mt-3">
                <div class="small text-muted">${totalItems} élément(s)</div>
                <nav aria-label="Pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                            <button class="page-link pagination-page" data-page="${currentPage - 1}" type="button">Précédent</button>
                        </li>
                        ${pageButtons}
                        <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                            <button class="page-link pagination-page" data-page="${currentPage + 1}" type="button">Suivant</button>
                        </li>
                    </ul>
                </nav>
            </div>`);
        }

        function renderRows() {
            const items = filteredItems();
            const pageItems = pagedItems(items);
            if (socialFeed) {
                $('#notifications-feed').html(pageItems.length ? pageItems.map(notificationHtml).join('') : `<div class="text-center text-muted py-4">{{ __('admin.empty') }}</div>`);
                renderPagination(items.length);
                return;
            }

            $('#resource-rows').html(pageItems.length ? groupedRows(pageItems) : `<tr><td colspan="${columns.length + 1}" class="text-center text-muted">{{ __('admin.empty') }}</td></tr>`);
            renderPagination(items.length);
        }

        function loadRows() {
            $.getJSON(endpoints.list, function (response) {
                allItems = response.items || [];
                currentPage = 1;
                renderRows();
            });
        }

        function applyUserMode(mode) {
            if (!userModes) return;

            const hiddenFields = userModes[mode]?.hidden || [];
            $('.user-mode-switch').each(function () {
                const active = $(this).data('user-mode') === mode;
                $(this).toggleClass('active btn-primary', active).toggleClass('btn-outline-primary', !active);
            });
            $('[data-field-wrapper]').each(function () {
                const name = String($(this).data('field-wrapper'));
                const hidden = hiddenFields.includes(name);
                $(this).toggleClass('d-none', hidden);
                $(this).find(':input').prop('disabled', hidden);
            });
            $('#partner_role_id').prop('disabled', mode !== 'partner');
        }

        function avatarPlaceholder() {
            return 'data:image/svg+xml;utf8,' + encodeURIComponent(`<svg xmlns="http://www.w3.org/2000/svg" width="160" height="160" viewBox="0 0 160 160"><rect width="160" height="160" rx="80" fill="#eef3f8"/><circle cx="80" cy="58" r="30" fill="#9aa8b8"/><path d="M28 138c8-34 28-52 52-52s44 18 52 52" fill="#9aa8b8"/></svg>`);
        }

        function setAvatarPreview(url) {
            if (!hasAvatarCropper) return;

            $('#admin-user-avatar-preview').attr('src', url || avatarPlaceholder());
        }

        function resetForm() {
            $('#resource-form')[0]?.reset();
            $('#item-id').val('');
            $('#resource-form input[type=checkbox]').prop('checked', false);
            $('#resource-form .file-url-input').each(function () {
                $(this).prop('required', $(this).data('required-on-create') == 1);
            });
            $('#descriptions-fields, #titles-fields').empty();
            refreshFilePreviews();
            setAvatarPreview('');
            $('#avatar_base64').val('');
            applyUserMode('user');
            filterProductCategories();
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
            (values.sub_dashes || []).forEach(subDash => addSubDash($block, subDash));
        }

        function addSubDash($dashBlock, values = {}) {
            const titleIdx = $dashBlock.closest('.child-block').data('title-index');
            const contentIdx = $dashBlock.closest('.content-block').data('content-index');
            const dashIdx = $dashBlock.data('dash-index');
            const subDashIdx = $dashBlock.find('.sub-dash-block').length;
            const html = $('#sub-dash-template').html()
                .replaceAll('__TITLE__', titleIdx)
                .replaceAll('__CONTENT__', contentIdx)
                .replaceAll('__DASH__', dashIdx)
                .replaceAll('__SUBDASH__', subDashIdx);
            const $block = $(html);
            $dashBlock.find('.sub-dashes-fields').append($block);
            fillTranslated($block, 'dash_content', values.dash_content);
        }

        function filterProductCategories() {
            if (resourceName !== 'products') return;

            const type = $('#type').val();
            const $category = $('#category_id');
            $category.find('option').each(function () {
                const optionType = $(this).data('for-type');
                const visible = !optionType || optionType === type;
                $(this).prop('hidden', !visible).prop('disabled', !visible);
            });

            if ($category.find('option:selected').prop('disabled')) {
                $category.val('');
            }
        }

        function fillTranslated($scope, key, value) {
            const translated = parseTranslated(value);
            ['fr', 'en', 'ln'].forEach(locale => {
                $scope.find(`[name$="[${key}][${locale}]"]`).val(translated?.[locale] || '');
            });
        }

        function renderFilePreview(field, url) {
            const $preview = $(`[data-preview-for="${field}"]`);
            if (!$preview.length) return;

            if (!url) {
                $preview.addClass('d-none').empty();
                return;
            }

            const escapedUrl = display(url);
            const media = field === 'media_url'
                ? `<video controls src="${escapedUrl}" class="rounded bg-dark"></video>`
                : `<img src="${escapedUrl}" class="rounded" alt="Aperçu">`;

            $preview.removeClass('d-none').html(`<div class="file-preview-box">
                ${media}
                <a class="btn btn-sm btn-outline-primary mt-2" href="${escapedUrl}" download target="_blank" rel="noopener">
                    <i class="bi bi-download"></i> Télécharger
                </a>
            </div>`);
        }

        function refreshFilePreviews(item = {}) {
            ['media_url', 'cover_url'].forEach(field => renderFilePreview(field, item[field] || ''));
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
                $('#resource-form .file-url-input').prop('required', false);
                applyUserMode(String(item.role_id || '') === String(partnerRoleId) ? 'partner' : 'user');
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
                refreshFilePreviews(item);
                filterProductCategories();
                setAvatarPreview(item.avatar_url || '');
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

        $(document).on('change', '.toggle-shared', function () {
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

        $(document).on('change', 'input[type="file"][name="media_url"], input[type="file"][name="cover_url"]', function () {
            const file = this.files?.[0];
            renderFilePreview(this.name, file ? URL.createObjectURL(file) : '');
        });

        $(document).on('click', '.toggle-password', function () {
            const $input = $('#' + $(this).data('target'));
            const hidden = $input.attr('type') === 'password';
            $input.attr('type', hidden ? 'text' : 'password');
            $(this).find('i').toggleClass('bi-eye', !hidden).toggleClass('bi-eye-slash', hidden);
        });

        $(document).on('click', '.user-mode-switch', function () {
            applyUserMode($(this).data('user-mode'));
        });

        $('#admin-user-avatar-input').on('change', function () {
            const file = this.files?.[0];
            if (!file || !hasAvatarCropper) return;

            const reader = new FileReader();
            reader.onload = function (event) {
                $('#admin-user-avatar-crop-image').attr('src', event.target.result);
                avatarModal = avatarModal || new bootstrap.Modal('#admin-user-avatar-modal');
                avatarModal.show();
            };
            reader.readAsDataURL(file);
        });

        $('#admin-user-avatar-modal').on('shown.bs.modal', function () {
            if (avatarCropper) {
                avatarCropper.destroy();
            }

            avatarCropper = new Cropper(document.getElementById('admin-user-avatar-crop-image'), {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 1,
                responsive: true,
                background: false,
            });
        }).on('hidden.bs.modal', function () {
            if (avatarCropper) {
                avatarCropper.destroy();
                avatarCropper = null;
            }
        });

        $('#admin-user-avatar-crop-save').on('click', function () {
            if (!avatarCropper) return;

            const dataUrl = avatarCropper.getCroppedCanvas({width: 512, height: 512}).toDataURL('image/png');
            $('#avatar_base64').val(dataUrl);
            setAvatarPreview(dataUrl);
            avatarModal?.hide();
        });

        $('#refresh-table').on('click', loadRows);
        $('#table-search, #group-filter, #role-filter').on('input change', function () {
            currentPage = 1;
            renderRows();
        });
        $('#type').on('change', filterProductCategories);
        $(document).on('click', '.pagination-page', function () {
            if ($(this).closest('.page-item').hasClass('disabled')) return;
            currentPage = Number($(this).data('page')) || 1;
            renderRows();
        });
        $('#new-item, #reset-form').on('click', resetForm);
        $('#add-description').on('click', () => addDescription());
        $('#add-title').on('click', () => addTitle());
        $(document).on('click', '.add-content', function () { addContent($(this).closest('.child-block')); });
        $(document).on('click', '.add-dash', function () { addDash($(this).closest('.content-block')); });
        $(document).on('click', '.add-sub-dash', function () { addSubDash($(this).closest('.dash-block')); });
        $(document).on('click', '.remove-child', function () { $(this).closest('.child-block, .content-block, .dash-block, .sub-dash-block').remove(); });
        applyUserMode('user');
        setAvatarPreview('');
        filterProductCategories();
        loadRows();
    });
</script>
@endpush
