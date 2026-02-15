@php
    $s = $state ?? [];
    $enabled = (bool) ($s['enabled'] ?? false);
    $showChevron = (bool) ($s['show_chevron'] ?? true);
    $modifyAlignment = (string) ($s['modify_alignment'] ?? 'left');
    $quantitySize = (string) ($s['quantity_size'] ?? 'medium');
    $popover = $s['popover'] ?? [];
    $quantityLabel = $s['quantity_label'] ?? [];
    $plusMinus = $s['plus_minus'] ?? [];

    $presetToPx = ['sm' => 240, 'md' => 320, 'lg' => 400, 'xl' => 480];
    $popoverPx = (($popover['mode'] ?? 'preset') === 'custom')
        ? (int) ($popover['px'] ?? 320)
        : ($presetToPx[$popover['preset'] ?? 'md'] ?? 320);
    $paddingMap = ['none' => '0px', 'tight' => '8px', 'base' => '12px', 'loose' => '16px'];
    $popoverPaddingX = $paddingMap[$popover['padding_x'] ?? 'base'] ?? '12px';

    $labelText = (string) ($quantityLabel['text'] ?? 'Quantity');
    $labelSize = (string) ($quantityLabel['size'] ?? 'medium');
    $labelAlignment = (string) ($quantityLabel['alignment'] ?? 'left');

    $alignMap = ['left' => 'flex-start', 'center' => 'center', 'right' => 'flex-end'];
    $labelAlignCss = $alignMap[$labelAlignment] ?? 'flex-start';
    $modifyAlignCss = $alignMap[$modifyAlignment] ?? 'flex-start';

    $sizeMap = ['small' => '12px', 'medium' => '14px', 'large' => '16px'];
    $labelFontSize = $sizeMap[$labelSize] ?? '14px';
    $qtyFontSize = $sizeMap[$quantitySize] ?? '14px';

    $kind = (string) ($plusMinus['kind'] ?? 'plain');
    $appearance = (string) ($plusMinus['appearance'] ?? 'monochrome');
    $btnSize = (string) ($plusMinus['size'] ?? 'small');
    $radius = (string) ($plusMinus['corner_radius'] ?? 'base');
    $radiusMap = ['none' => '0px', 'small' => '6px', 'base' => '10px', 'large' => '14px', 'fullyRounded' => '9999px'];
    $btnRadius = $radiusMap[$radius] ?? '10px';

    $btnPaddingMap = ['small' => '4px 8px', 'medium' => '6px 10px', 'large' => '8px 12px'];
    $btnPadding = $btnPaddingMap[$btnSize] ?? '4px 8px';
    $btnFontMap = ['small' => '12px', 'medium' => '13px', 'large' => '14px'];
    $btnFont = $btnFontMap[$btnSize] ?? '12px';

    $btnBg = '#ffffff';
    $btnBorder = '#d1d5db';
    $btnColor = '#111827';
    if ($kind === 'primary') {
        $btnBg = '#111827';
        $btnBorder = '#111827';
        $btnColor = '#ffffff';
    } elseif ($kind === 'secondary') {
        $btnBg = '#f9fafb';
        $btnBorder = '#9ca3af';
        $btnColor = '#111827';
    }
    if ($appearance === 'critical') {
        $btnColor = '#b91c1c';
        if ($kind === 'primary') {
            $btnBg = '#b91c1c';
            $btnBorder = '#b91c1c';
            $btnColor = '#ffffff';
        }
    } elseif ($appearance === 'monochrome') {
        $btnColor = '#111827';
    }
@endphp

<div class="space-y-4">
    <div class="text-xs text-gray-500 dark:text-gray-400">
        Live preview of cart line popup (sample product). This is a visual approximation of Checkout UI.
    </div>

    <div style="border:1px solid #e5e7eb;border-radius:12px;padding:12px;background:#fff;max-width:700px;">
        <div style="display:flex;justify-content:space-between;align-items:center;gap:10px;margin-bottom:8px;">
            <div>
                <div style="font-size:14px;font-weight:600;color:#111827;">Sample Product - Lemon Gummies</div>
                <div style="font-size:12px;color:#6b7280;">1 x $29.00</div>
            </div>
            <div style="font-size:13px;font-weight:600;color:#111827;">$29.00</div>
        </div>

        <div style="display:flex;justify-content:{{ $modifyAlignCss }};">
            @if($enabled)
                <button type="button" style="border:1px solid {{ $btnBorder }};background:{{ $btnBg }};color:{{ $btnColor }};border-radius:{{ $btnRadius }};padding:{{ $btnPadding }};font-size:{{ $btnFont }};">
                    Modify @if($showChevron) <span style="opacity:0.8;">▼</span> @endif
                </button>
            @else
                <span style="font-size:12px;color:#9ca3af;">Quantity on cart lines is off</span>
            @endif
        </div>
    </div>

    @if($enabled)
        <div style="border:1px solid #d1d5db;border-radius:12px;background:#ffffff;width:{{ $popoverPx }}px;max-width:100%;padding:12px {{ $popoverPaddingX }};">
            <div style="display:flex;justify-content:{{ $labelAlignCss }};margin-bottom:8px;">
                <div style="font-size:{{ $labelFontSize }};color:#6b7280;font-weight:500;">{{ $labelText }}</div>
            </div>
            <div style="display:flex;justify-content:center;align-items:center;gap:8px;margin-bottom:10px;">
                <button type="button" style="border:1px solid {{ $btnBorder }};background:{{ $btnBg }};color:{{ $btnColor }};border-radius:{{ $btnRadius }};padding:{{ $btnPadding }};font-size:{{ $btnFont }};">-</button>
                <div style="font-size:{{ $qtyFontSize }};font-weight:600;min-width:24px;text-align:center;">1</div>
                <button type="button" style="border:1px solid {{ $btnBorder }};background:{{ $btnBg }};color:{{ $btnColor }};border-radius:{{ $btnRadius }};padding:{{ $btnPadding }};font-size:{{ $btnFont }};">+</button>
            </div>
            <div style="text-align:center;">
                <button type="button" style="border:none;background:transparent;color:#6b7280;font-size:12px;text-decoration:underline;">Remove</button>
            </div>
        </div>
    @endif
</div>
