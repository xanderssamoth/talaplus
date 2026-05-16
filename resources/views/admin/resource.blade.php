@extends('layouts.admin')

@section('title', __('admin.'.$resource))

@section('content')
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
                    <div class="table-responsive">
                        <table class="table table-hover data-table">
                            <thead>
                            <tr>
                                @foreach ($config['columns'] as $column)
                                    <th>{{ str($column)->replace('_', ' ')->title() }}</th>
                                @endforeach
                                @unless ($config['readonly'] ?? false)
                                    <th class="text-end">{{ __('admin.actions') }}</th>
                                @endunless
                            </tr>
                            </thead>
                            <tbody id="resource-rows">
                            <tr><td colspan="{{ count($config['columns']) + 1 }}" class="text-center text-muted">{{ __('admin.empty') }}</td></tr>
                            </tbody>
                        </table>
                    </div>
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

                        <form id="resource-form">
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
                                @else
                                    <div class="mb-3">
                                        <label class="form-label" for="{{ $name }}">{{ $field['label'] }}</label>
                                        <input class="form-control" id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" step="{{ $field['step'] ?? '' }}" {{ $required }}>
                                    </div>
                                @endif
                            @endforeach

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
@endsection

@push('scripts')
<script>
    $(function () {
        const endpoints = {
            list: @json(route($resource.'.data')),
            store: @json(route($resource.'.store')),
            show: (id) => @json(url($resource)) + '/' + id,
            update: (id) => @json(url($resource)) + '/' + id,
            destroy: (id) => @json(url($resource)) + '/' + id,
        };
        const columns = @json($config['columns']);
        const groupBy = @json($config['group_by'] ?? null);
        const readonly = @json($config['readonly'] ?? false);

        $.ajaxSetup({
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
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

        function rowHtml(item) {
            const cells = columns.map(column => `<td>${display(item[column + '_display'] ?? item[column], column)}</td>`).join('');
            const actions = readonly ? '' : `<td class="text-end">
                <button class="btn btn-sm btn-outline-primary edit-item" data-id="${item.id}" type="button"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-sm btn-outline-danger delete-item" data-id="${item.id}" type="button"><i class="bi bi-trash"></i></button>
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
                const title = key === 'money' ? @json(__('admin.money')) : (key === 'percentage' ? @json(__('admin.percentage')) : key);
                return `<tr class="table-light"><th colspan="${columns.length + (readonly ? 0 : 1)}">${title}</th></tr>` + groups[key].map(rowHtml).join('');
            }).join('');
        }

        function loadRows() {
            $.getJSON(endpoints.list, function (response) {
                const items = response.items || [];
                $('#resource-rows').html(items.length ? groupedRows(items) : `<tr><td colspan="${columns.length + 1}" class="text-center text-muted">{{ __('admin.empty') }}</td></tr>`);
            });
        }

        function resetForm() {
            $('#resource-form')[0]?.reset();
            $('#item-id').val('');
            $('#resource-form input[type=checkbox]').prop('checked', false);
        }

        function parseTranslated(value) {
            if (!value) return {};
            if (typeof value === 'string') {
                try { return JSON.parse(value); } catch (e) { return {fr: value}; }
            }
            return value;
        }

        $('#resource-form').on('submit', function (event) {
            event.preventDefault();
            const id = $('#item-id').val();
            const method = id ? 'PUT' : 'POST';
            const url = id ? endpoints.update(id) : endpoints.store;

            $.ajax({url, method, data: $(this).serialize()})
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
                    } else {
                        $(`[name="${key}"]`).val(value);
                    }
                });
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
        $('#new-item, #reset-form').on('click', resetForm);
        loadRows();
    });
</script>
@endpush
