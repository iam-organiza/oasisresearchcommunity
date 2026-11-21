{{--
    Opens a section: its name in small type followed by a thin rule. Expects $label.
    Optional: $tag (default p; pass h2 when the label is the section's only heading) and $tight (less space, for lists inside a page).
--}}
@php($tag = $tag ?? 'p')
<{{ $tag }} @class(['text-caption01 font-semibold opacity-70', 'section-divider' => empty($tight), 'section-divider section-divider-tight' => ! empty($tight)])>{{ $label }}</{{ $tag }}>
