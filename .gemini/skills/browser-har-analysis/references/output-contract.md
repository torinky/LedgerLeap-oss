# HAR Output Contract

When reporting results, include:

1. **Capture context**
   - HAR filename
   - debug mode on/off
   - browser / page flow if known

2. **Top-level metrics**
   - total requests
   - largest `document`
   - `livewire/update` count and sizes
   - obvious static asset outliers

3. **Component breakdown**
   - repeated Livewire components
   - response sizes per component
   - whether the same heavy component appears more than once

4. **Folder-switch analysis** (most important after `#[Lazy]`)
   - `lazy%`: is Lazy separation working?
   - `IM_med`: interactive time median (folder click → skeleton display)
   - `RT_med`: RecordsTable standalone load time
   - content complete = IM + RT

5. **Performance log cross-check** (`analyze_perf_log.py`)
   - `textarea` sum as % of total
   - >20ms spike render_kinds
   - wait ≈ total → server-side is the main cause

6. **Comparison summary**
   - before / after deltas
   - what disappeared
   - what remains

7. **Next action**
   - whether to keep investigating network/DOM/UI layering
   - whether the bottleneck has moved to HTML, assets, or rerenders
