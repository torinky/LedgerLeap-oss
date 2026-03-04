# Alpine.js Init Overlay Pattern (since Issue #77)

## Problem

After Livewire HTTP completes, Alpine.js still initialises all
`expandable-content` components (DOM height measurement × records × columns).
During this time buttons are unresponsive but `wire:loading` shows nothing —
Livewire has no request in flight.

```
① Livewire HTTP response received
② Alpine alpine:init fires
③ Each expandable-content init() → requestIdleCallback → checkOverflow()
   (scrollHeight measurement × records × columns)
④ All components measured → buttons become interactive
   ← wire:loading cannot cover this gap
```

## Solution: ledgerInitOverlay Alpine Component

```js
// resources/js/components/ledger-init-overlay.js
export default () => ({
    visible: true,
    t0: Date.now(),
    hide() {
        this.visible = false;
        console.log('[INIT-TIMING] overlay hidden at', Date.now() - this.t0, 'ms');
    },
    startFallbackTimer() {
        setTimeout(() => { if (this.visible) this.hide(); }, 3000);
    },
});
```

```blade
{{-- Inside Livewire root <div>, index-manager.blade.php --}}
<div x-data="ledgerInitOverlay()"
     x-init="startFallbackTimer()"
     x-on:livewire:navigated.window.once="hide()"
     x-show="visible"
     class="fixed inset-0 z-50 flex items-center justify-center bg-base-100/60 backdrop-blur-sm">
    <span class="loading loading-spinner loading-lg text-primary"></span>
</div>
```

**Register in app.js** (inside `document.addEventListener('alpine:init', ...)` block):
```js
import ledgerInitOverlay from './components/ledger-init-overlay.js';
Alpine.data('ledgerInitOverlay', ledgerInitOverlay);
```

## ⚠️ Critical Pitfalls

### Pitfall 1 — `@livewire:navigated` vs `x-on:livewire:navigated`

`@livewire:navigated` inside Blade is parsed as a **Blade directive** and throws
a compile error. Always use `x-on:livewire:navigated.window.once` in attributes.

```blade
{{-- ❌ WRONG — Blade compile error --}}
@livewire:navigated.window.once="hide()"

{{-- ✅ CORRECT --}}
x-on:livewire:navigated.window.once="hide()"
```

### Pitfall 2 — inline `x-data` with method shorthand

Method shorthand (`{ hide() {} }`) inside an inline `x-data="{ ... }"` attribute
is misinterpreted by PHP as a closure. Always register with `Alpine.data()`.

```blade
{{-- ❌ WRONG — PHP parse error --}}
<div x-data="{ hide() { this.visible = false; } }">

{{-- ✅ CORRECT --}}
<div x-data="ledgerInitOverlay()">
```

### Pitfall 3 — Blade view cache

After fixing Blade syntax errors, run `php artisan view:clear`. Old cached files
continue throwing errors even after the source is fixed.

## requestIdleCallback for Expensive Alpine Init

Spread `checkOverflow()` (DOM height measurement) across browser idle frames:

```js
// expandable-content.js
init() {
    const measure = () => this.checkOverflow();
    if (typeof requestIdleCallback !== 'undefined') {
        requestIdleCallback(measure, { timeout: 500 });
    } else {
        setTimeout(measure, 200); // Safari fallback
    }
    let resizeTimer = null;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => { this._measured = false; this.checkOverflow(); }, 150);
    });
},
checkOverflow() {
    if (this._measured) return; // guard against x-intersect re-fire
    const maxH = parseFloat(window.getComputedStyle(this.$refs.content).maxHeight);
    this.showToggle = this.$refs.content.scrollHeight > maxH + 1;
    this._measured = true;
},
```

Use `x-intersect.once.threshold.10="checkOverflow()"` (`.once` prevents re-fire
on scroll-back). The `_measured` flag acts as a secondary guard.

## Observed Timing (since #77)

Safari: 681–1092ms visible. Chrome: 47–66ms (barely perceptible).
Fallback timer set to 3000ms ensures overlay always clears.

