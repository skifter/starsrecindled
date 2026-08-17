<script lang="ts">
  export let color = '#47c8ff';
  export let sensorRange = 0;
  export let size = 46;
  export let neutral = false;
  export let label = 'Planet';

  $: range = Math.max(0, Math.min(3, Math.round(sensorRange)));
</script>

<div
  class="sensor-visual"
  class:neutral
  style={`--sensor-color:${color};--sensor-size:${size}px`}
  role="img"
  aria-label={`${label}; sensor range ${range}`}
>
  {#if range >= 3}<span class="orbit orbit-3"></span>{/if}
  {#if range >= 2}<span class="orbit orbit-2"></span>{/if}
  {#if range >= 1}<span class="orbit orbit-1"></span>{/if}
  <span class="sphere"><i></i></span>
  {#if range > 0}<b>{range}</b>{/if}
</div>

<style>
  .sensor-visual{position:relative;width:var(--sensor-size);height:var(--sensor-size);display:grid;place-items:center;flex:none;color:var(--sensor-color)}
  .sphere{position:relative;z-index:3;width:34%;height:34%;border-radius:50%;background:radial-gradient(circle at 32% 28%,#edfaff 0 8%,color-mix(in srgb,var(--sensor-color) 72%,#dceeff) 18%,color-mix(in srgb,var(--sensor-color) 48%,#142535) 58%,#06111c 100%);border:1px solid color-mix(in srgb,var(--sensor-color) 78%,#dff7ff);box-shadow:0 0 calc(var(--sensor-size) * .22) color-mix(in srgb,var(--sensor-color) 35%,transparent)}
  .sphere i{position:absolute;left:12%;right:14%;top:47%;height:1px;background:color-mix(in srgb,var(--sensor-color) 48%,transparent);transform:rotate(-12deg)}
  .orbit{position:absolute;z-index:1;border:1px solid var(--sensor-color);border-radius:48% 52% 46% 54%;opacity:.8;box-shadow:0 0 6px color-mix(in srgb,var(--sensor-color) 24%,transparent)}
  .orbit-1{width:58%;height:36%;transform:rotate(-18deg)}
  .orbit-2{width:76%;height:49%;transform:rotate(31deg);opacity:.62}
  .orbit-3{width:94%;height:61%;transform:rotate(-51deg);opacity:.46;border-style:dashed}
  b{position:absolute;z-index:4;right:0;bottom:0;min-width:14px;height:14px;display:grid;place-items:center;border:1px solid color-mix(in srgb,var(--sensor-color) 65%,transparent);background:#06131f;color:var(--sensor-color);font-size:8px;font-weight:600;line-height:1}
  .neutral{--sensor-color:#8aa0ad!important;filter:saturate(.3)}
</style>
