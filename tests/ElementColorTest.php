<?php

use Native\Mobile\Edge\CallbackRegistry;
use Native\Mobile\Edge\ElementRegistry;
use Native\Mobile\Edge\NativeElementCollector;
use Native\Mobile\Edge\TailwindParser;
use Native\Mobile\UI\Elements\ActivityIndicator;
use Native\Mobile\UI\Elements\BareTextInput;
use Native\Mobile\UI\Elements\FilledTextInput;
use Native\Mobile\UI\Elements\Icon;
use Native\Mobile\UI\Elements\ListItem;
use Native\Mobile\UI\Elements\OutlinedTextInput;
use Native\Mobile\UI\Elements\ProgressBar;

/**
 * Element color props share the theme config's authoring grammar:
 * Tailwind palette names, opacity modifiers, and CSS alpha hex all
 * resolve to wire-format hex before hitting the bridge.
 */
beforeEach(function () {
    NativeElementCollector::reset();
    TailwindParser::clearCache();
    ElementRegistry::reset();
    ElementRegistry::register('activity_indicator', ActivityIndicator::class);
    ElementRegistry::register('bare_text_input', BareTextInput::class);
    ElementRegistry::register('icon', Icon::class);
    ElementRegistry::register('list_item', ListItem::class);
    ElementRegistry::register('outlined_text_input', OutlinedTextInput::class);
    ElementRegistry::register('filled_text_input', FilledTextInput::class);
    ElementRegistry::register('progress_bar', ProgressBar::class);
});

afterEach(function () {
    NativeElementCollector::reset();
    ElementRegistry::reset();
});

function collectProps(string $type, array $attrs): array
{
    NativeElementCollector::leaf($type, $attrs);

    return NativeElementCollector::collect()->toArray(new CallbackRegistry)['props'];
}

it('resolves palette names on progress bar colors', function () {
    $props = collectProps('progress_bar', [
        'color' => 'red-300',
        'track-color' => 'red-300/20',
    ]);

    expect($props['color'])->toBe('#FCA5A5');
    expect($props['track_color'])->toBe('#33FCA5A5');
});

it('resolves CSS alpha hex on icon colors', function () {
    $props = collectProps('icon', [
        'name' => 'home',
        'color' => '#8B5CF680',
        'dark-color' => 'violet-300/50',
    ]);

    expect($props['color'])->toBe('#808B5CF6');
    expect($props['dark_color'])->toBe('#80C4B5FD');
});

it('resolves activity indicator and bare input colors', function () {
    expect(collectProps('activity_indicator', ['color' => 'teal-700'])['color'])
        ->toBe('#0F766E');

    expect(collectProps('bare_text_input', ['color' => 'slate-700'])['color'])
        ->toBe('#334155');
});

it('resolves list item color props', function () {
    $props = collectProps('list_item', [
        'headline' => 'Inbox',
        'headlineColor' => 'red-300',
        'containerColor' => '#8B5CF6/50',
        'leadingIconBgColor' => 'orange-800',
    ]);

    expect($props['headline_color'])->toBe('#FCA5A5');
    expect($props['container_color'])->toBe('#808B5CF6');
    expect($props['leading_icon_bg_color'])->toBe('#9A3412');
});

it('resolves colors inside badge and swipe-action payloads', function () {
    $props = collectProps('list_item', [
        'headline' => 'Inbox',
        'trailing-badges' => [
            ['icon' => 'flag', 'color' => 'red-500'],
            ['icon' => 'pin'],
        ],
        'trailing-actions' => [
            ['method' => 'archive', 'label' => 'Archive', 'tint' => 'blue-500/50'],
            ['method' => 'delete', 'label' => 'Delete'],
        ],
    ]);

    $badges = json_decode($props['trailing_badges_json'], true);
    expect($badges[0]['color'])->toBe('#EF4444');
    expect($badges[1]['color'])->toBe('');

    $actions = json_decode($props['trailing_actions_json'], true);
    expect($actions[0]['tint'])->toBe('#803B82F6');
    expect($actions[1]['tint'])->toBe('');
});

it('passes plain hex and unknown strings through untouched', function () {
    $props = collectProps('progress_bar', ['color' => '#B91C1C']);
    expect($props['color'])->toBe('#B91C1C');

    $props = collectProps('icon', ['name' => 'home', 'color' => 'chartreuse']);
    expect($props['color'])->toBe('chartreuse');
});

it('resolves independent text input icon colors from kebab-case attributes', function (string $type) {
    $props = collectProps($type, [
        'leading-icon' => 'email',
        'leading-icon-color' => 'amber-600',
        'dark-leading-icon-color' => 'amber-300/50',
        'trailing-icon' => 'close',
        'trailing-icon-color' => '#8B5CF680',
        'dark-trailing-icon-color' => '#0F766E',
    ]);

    expect($props)
        ->toMatchArray([
            'leading_icon_color' => '#D97706',
            'dark_leading_icon_color' => '#80FCD34D',
            'trailing_icon_color' => '#808B5CF6',
            'dark_trailing_icon_color' => '#0F766E',
        ]);
})->with(['outlined_text_input', 'filled_text_input']);

it('resolves text input icon colors from camelCase attributes', function (string $type) {
    $props = collectProps($type, [
        'leadingIconColor' => 'red-300',
        'darkLeadingIconColor' => 'red-300/20',
        'trailingIconColor' => 'teal-700',
        'darkTrailingIconColor' => '#8B5CF6/50',
    ]);

    expect($props)
        ->toMatchArray([
            'leading_icon_color' => '#FCA5A5',
            'dark_leading_icon_color' => '#33FCA5A5',
            'trailing_icon_color' => '#0F766E',
            'dark_trailing_icon_color' => '#808B5CF6',
        ]);
})->with(['outlined_text_input', 'filled_text_input']);

it('preserves fully transparent text input icon colors in the payload', function (string $type, string $color, string $expected) {
    $props = collectProps($type, [
        'leading-icon-color' => $color,
        'dark-trailing-icon-color' => $color,
    ]);

    expect($props)
        ->toHaveKey('leading_icon_color', $expected)
        ->toHaveKey('dark_trailing_icon_color', $expected);
})->with([
    ['outlined_text_input', 'transparent', '#00000000'],
    ['outlined_text_input', 'red-300/0', '#00FCA5A5'],
    ['filled_text_input', '#00000000', '#00000000'],
]);

it('passes unknown text input icon colors through for native fallback', function (string $type) {
    $props = collectProps($type, ['leading-icon-color' => 'not-a-color']);

    expect($props)->toHaveKey('leading_icon_color', 'not-a-color');
})->with(['outlined_text_input', 'filled_text_input']);

it('serializes only text input icon colors explicitly set through the fluent API', function (string $inputClass) {
    $props = $inputClass::make()
        ->leadingIconColor('amber-600')
        ->darkTrailingIconColor('violet-300/50')
        ->getResolvedProps(new CallbackRegistry);

    expect($props)
        ->toHaveKey('leading_icon_color', '#D97706')
        ->toHaveKey('dark_trailing_icon_color', '#80C4B5FD')
        ->not->toHaveKeys(['dark_leading_icon_color', 'trailing_icon_color']);
})->with([OutlinedTextInput::class, FilledTextInput::class]);

it('omits text input icon color props when no override is provided', function (string $inputClass) {
    $props = $inputClass::make()
        ->leadingIcon('email')
        ->trailingIcon('close')
        ->getResolvedProps(new CallbackRegistry);

    expect($props)->not->toHaveKeys([
        'leading_icon_color',
        'dark_leading_icon_color',
        'trailing_icon_color',
        'dark_trailing_icon_color',
    ]);
})->with([OutlinedTextInput::class, FilledTextInput::class]);
