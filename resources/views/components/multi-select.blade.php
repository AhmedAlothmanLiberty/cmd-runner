@props([
    'name',
    'options' => [],
    'selected' => [],
    'label' => null,
    'placeholder' => 'Select options',
    'multiple' => true,
    'id' => null,
])

@php
    $isMultiple = (bool) $multiple;
    $selectedValues = array_map('strval', is_array($selected) ? $selected : (array) $selected);
    $selectId = $id ?: ('multi-select-' . md5($name . implode(',', $selectedValues)));
    $fieldName = $isMultiple && ! str_ends_with($name, '[]') ? $name . '[]' : $name;
@endphp

@once
    @push('styles')
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/choices.js/public/assets/styles/choices.min.css">
        <style>
            .choices {
                min-height: 38px;
            }
            .choices__inner {
                background-color: #fff;
                border-radius: 0.375rem;
                border: 1px solid #ced4da;
                min-height: 38px;
                padding: 0.25rem 0.5rem;
            }
            .choices__list--multiple .choices__item {
                background-color: #e0f2fe;
                border: 1px solid #bae6fd;
                color: #0c4a6e;
                font-size: 0.75rem;
            }
            .choices__list--dropdown .choices__item--selectable.is-highlighted {
                background: #e0f2fe;
            }
        </style>
    @endpush
    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/choices.js/public/assets/scripts/choices.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                document.querySelectorAll('[data-choices-multi]').forEach((el) => {
                    if (el.dataset.choicesReady) return;
                    const placeholder = el.dataset.placeholder || 'Select options';
                    new Choices(el, {
                        removeItemButton: true,
                        placeholder: true,
                        placeholderValue: placeholder,
                        shouldSort: false,
                        searchResultLimit: 50,
                    });
                    el.dataset.choicesReady = 'true';
                });
            });
        </script>
    @endpush
@endonce

@if ($label)
    <label for="{{ $selectId }}" class="form-label mb-1">{{ $label }}</label>
@endif
<select
    id="{{ $selectId }}"
    name="{{ $fieldName }}"
    class="form-select"
    @if ($isMultiple) multiple @endif
    data-choices-multi
    data-placeholder="{{ $placeholder }}"
>
    @foreach ($options as $value => $text)
        <option value="{{ $value }}" @selected(in_array((string) $value, $selectedValues, true))>{{ $text }}</option>
    @endforeach
</select>
