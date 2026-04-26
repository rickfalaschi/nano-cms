<?php

declare(strict_types=1);

namespace Nano;

final class FieldRenderer
{
    /**
     * Render a field input given its definition, current value, and form name prefix.
     */
    public static function render(array $field, mixed $value, string $namePrefix = 'fields'): string
    {
        $type = (string) ($field['type'] ?? 'text');
        $name = (string) ($field['name'] ?? '');
        $label = (string) ($field['label'] ?? $name);
        $help = (string) ($field['help'] ?? '');
        $required = (bool) ($field['required'] ?? false);
        $inputName = $namePrefix . '[' . $name . ']';
        $inputId = preg_replace('/[^a-z0-9_]/i', '_', $namePrefix . '_' . $name) ?? $name;

        $body = match ($type) {
            'text' => self::renderInput('text', $inputName, $inputId, $value, $field),
            'email' => self::renderInput('email', $inputName, $inputId, $value, $field),
            'url' => self::renderInput('url', $inputName, $inputId, $value, $field),
            'number' => self::renderInput('number', $inputName, $inputId, $value, $field),
            'date' => self::renderInput('date', $inputName, $inputId, $value, $field),
            'textarea' => self::renderTextarea($inputName, $inputId, $value, $field),
            'richtext' => self::renderRichText($inputName, $inputId, $value, $field),
            'image' => self::renderImage($inputName, $inputId, $value, $field),
            'select' => self::renderSelect($inputName, $inputId, $value, $field),
            'boolean' => self::renderBoolean($inputName, $inputId, $value, $field),
            'repeater' => self::renderRepeater($inputName, $inputId, $value, $field),
            default => self::renderInput('text', $inputName, $inputId, $value, $field),
        };

        $helpHtml = $help !== '' ? '<p class="field__help">' . e($help) . '</p>' : '';
        $required = $required ? ' <span class="field__required">*</span>' : '';

        return sprintf(
            '<div class="field field--%s" data-field-type="%s" data-field-name="%s">
                <label class="field__label" for="%s">%s%s</label>
                <div class="field__control">%s</div>
                %s
            </div>',
            e($type),
            e($type),
            e($name),
            e($inputId),
            e($label),
            $required,
            $body,
            $helpHtml
        );
    }

    /**
     * Collect POSTed values into a clean array, applying field definitions.
     */
    public static function collect(array $fieldDefs, mixed $postData): array
    {
        if (!is_array($postData)) {
            $postData = [];
        }

        $result = [];
        foreach ($fieldDefs as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') continue;
            $type = (string) ($field['type'] ?? 'text');
            $raw = $postData[$name] ?? null;

            $result[$name] = match ($type) {
                'boolean' => !empty($raw) && $raw !== '0',
                'number' => $raw === null || $raw === '' ? null : (is_numeric($raw) ? $raw + 0 : null),
                'repeater' => self::collectRepeater($field, $raw),
                default => is_string($raw) ? $raw : ($raw === null ? null : $raw),
            };
        }
        return $result;
    }

    private static function collectRepeater(array $field, mixed $raw): array
    {
        $rows = [];
        if (!is_array($raw)) return $rows;

        $subFields = (array) ($field['fields'] ?? []);
        foreach ($raw as $key => $row) {
            if ($key === '__template__') continue;
            if (!is_array($row)) continue;
            $rows[] = self::collect($subFields, $row);
        }
        return array_values($rows);
    }

    private static function renderInput(string $htmlType, string $name, string $id, mixed $value, array $field): string
    {
        $required = !empty($field['required']) ? ' required' : '';
        $placeholder = isset($field['placeholder']) ? ' placeholder="' . e((string) $field['placeholder']) . '"' : '';
        return sprintf(
            '<input class="input" type="%s" name="%s" id="%s" value="%s"%s%s>',
            e($htmlType),
            e($name),
            e($id),
            e($value),
            $placeholder,
            $required
        );
    }

    private static function renderTextarea(string $name, string $id, mixed $value, array $field): string
    {
        $rows = (int) ($field['rows'] ?? 4);
        return sprintf(
            '<textarea class="input input--textarea" name="%s" id="%s" rows="%d">%s</textarea>',
            e($name),
            e($id),
            $rows,
            e($value)
        );
    }

    private static function renderRichText(string $name, string $id, mixed $value, array $field): string
    {
        return sprintf(
            '<div class="richtext" data-richtext>
                <div class="richtext__toolbar" data-richtext-toolbar>
                    <button type="button" data-cmd="bold" title="Negrito"><strong>B</strong></button>
                    <button type="button" data-cmd="italic" title="Itálico"><em>I</em></button>
                    <button type="button" data-cmd="strike" title="Riscado"><s>S</s></button>
                    <button type="button" data-cmd="paragraph" title="Parágrafo">P</button>
                    <button type="button" data-cmd="h2" title="Título 2">H2</button>
                    <button type="button" data-cmd="h3" title="Título 3">H3</button>
                    <button type="button" data-cmd="bulletList" title="Lista">• Lista</button>
                    <button type="button" data-cmd="orderedList" title="Lista numerada">1. Lista</button>
                    <button type="button" data-cmd="link" title="Link">Link</button>
                    <button type="button" data-cmd="undo" title="Desfazer">↶</button>
                    <button type="button" data-cmd="redo" title="Refazer">↷</button>
                </div>
                <div class="richtext__editor" data-richtext-editor></div>
                <textarea class="richtext__source" name="%s" id="%s" hidden>%s</textarea>
            </div>',
            e($name),
            e($id),
            e($value)
        );
    }

    private static function renderImage(string $name, string $id, mixed $value, array $field): string
    {
        $mediaId = is_numeric($value) ? (int) $value : 0;
        $media = $mediaId > 0 ? \Nano\Models\Media::find($mediaId) : null;

        $previewHtml = '';
        $hasMedia = $media !== null;
        if ($media !== null) {
            $previewSrc = $media->isImage() ? $media->url('thumb') : '';
            $previewHtml = sprintf(
                '<div class="image-field__current">
                    %s
                    <div class="image-field__meta">
                        <strong>%s</strong>
                        <span class="muted">%s · %s%s</span>
                    </div>
                </div>',
                $previewSrc !== ''
                    ? '<img class="image-field__current-img" src="' . e($previewSrc) . '" alt="">'
                    : '<div class="image-field__current-doc">' . e(strtoupper(pathinfo($media->filename, PATHINFO_EXTENSION))) . '</div>',
                e($media->originalName),
                e($media->humanSize()),
                e($media->mime),
                $media->width && $media->height ? ' · ' . $media->width . '×' . $media->height : ''
            );
        }

        return sprintf(
            '<div class="image-field" data-image-field>
                <input type="hidden" name="%s" id="%s" value="%s" data-image-field-input>
                <div class="image-field__preview" data-image-field-preview>%s</div>
                <div class="image-field__actions">
                    <button type="button" class="button button--small button--ghost" data-image-field-pick>%s</button>
                    <button type="button" class="button button--small" data-image-field-clear%s>Remover</button>
                </div>
            </div>',
            e($name),
            e($id),
            e((string) $mediaId),
            $previewHtml,
            $hasMedia ? 'Trocar imagem' : 'Selecionar imagem',
            $hasMedia ? '' : ' hidden'
        );
    }

    private static function renderSelect(string $name, string $id, mixed $value, array $field): string
    {
        $options = (array) ($field['options'] ?? []);
        $html = '<select class="input input--select" name="' . e($name) . '" id="' . e($id) . '">';
        if (!empty($field['placeholder'])) {
            $html .= '<option value="">' . e((string) $field['placeholder']) . '</option>';
        }
        foreach ($options as $optValue => $optLabel) {
            $selected = ((string) $value === (string) $optValue) ? ' selected' : '';
            $html .= '<option value="' . e((string) $optValue) . '"' . $selected . '>' . e((string) $optLabel) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private static function renderBoolean(string $name, string $id, mixed $value, array $field): string
    {
        $checked = !empty($value) ? ' checked' : '';
        return sprintf(
            '<label class="boolean-field"><input type="checkbox" name="%s" id="%s" value="1"%s> <span>%s</span></label>',
            e($name),
            e($id),
            $checked,
            e((string) ($field['inline_label'] ?? 'Sim'))
        );
    }

    private static function renderRepeater(string $name, string $id, mixed $value, array $field): string
    {
        $subFields = (array) ($field['fields'] ?? []);
        $rows = is_array($value) ? array_values($value) : [];

        $rowsHtml = '';
        foreach ($rows as $i => $row) {
            $rowsHtml .= self::renderRepeaterRow($name, $i, $subFields, is_array($row) ? $row : []);
        }

        // The template HTML for a fresh row goes inside a <template> tag.
        // We base64-encode it so:
        //   1. The outer markup never confuses the HTML parser (e.g. when a
        //      nested repeater's inner </template> would prematurely close us).
        //   2. The JS can decode and stamp __INDEX__ cleanly.
        $template = self::renderRepeaterRow($name, '__INDEX__', $subFields, []);

        return sprintf(
            '<div class="repeater" data-repeater data-repeater-name="%s">
                <div class="repeater__rows" data-repeater-rows>%s</div>
                <script type="text/template" data-repeater-template>%s</script>
                <button type="button" class="button button--small" data-repeater-add>+ Adicionar</button>
            </div>',
            e($name),
            $rowsHtml,
            base64_encode($template)
        );
    }

    private static function renderRepeaterRow(string $name, int|string $index, array $subFields, array $values): string
    {
        $rowName = $name . '[' . $index . ']';
        $body = '';
        foreach ($subFields as $sub) {
            $subName = (string) ($sub['name'] ?? '');
            $subValue = $values[$subName] ?? null;
            $body .= self::render($sub, $subValue, $rowName);
        }
        return sprintf(
            '<div class="repeater__row" data-repeater-row><div class="repeater__row-body">%s</div><button type="button" class="repeater__remove" data-repeater-remove aria-label="Remover">×</button></div>',
            $body
        );
    }
}
