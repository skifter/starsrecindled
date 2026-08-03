<script lang="ts">
  export let name = 'galaxy';
  export let size = 22;
  export let strokeWidth = 1.7;
</script>

<svg
  class="icon"
  width={size}
  height={size}
  viewBox="0 0 24 24"
  fill="none"
  stroke="currentColor"
  stroke-width={strokeWidth}
  stroke-linecap="round"
  stroke-linejoin="round"
  aria-hidden="true"
>
  {#if name === 'galaxy'}
    <path d="M12 3c4.8 0 8.5 2.6 8.5 5.8 0 2.8-3 5-6.8 5-3.2 0-5.8-1.6-5.8-3.7 0-1.8 2-3.2 4.5-3.2 2 0 3.7 1 3.7 2.3 0 1.1-1.2 1.9-2.7 1.9" />
    <path d="M12 21c-4.8 0-8.5-2.6-8.5-5.8 0-2.8 3-5 6.8-5 3.2 0 5.8 1.6 5.8 3.7 0 1.8-2 3.2-4.5 3.2-2 0-3.7-1-3.7-2.3 0-1.1 1.2-1.9 2.7-1.9" />
  {:else if name === 'planet'}
    <circle cx="12" cy="12" r="5.5" /><path d="M3 15.5c2.8 1 7.5.1 11.6-2 4-2 6.8-4.5 6.1-5.9-.5-1-2.6-1.1-5.2-.4" /><path d="M8.4 17c-3 .5-5.2.1-5.8-1-.7-1.5 2-4 6.1-6.1 4-2 8.7-2.9 11.4-1.9" />
  {:else if name === 'fleet'}
    <path d="M12 2.5 19 20l-7-3.6L5 20 12 2.5Z" /><path d="m9.5 13 2.5-6 2.5 6-2.5 1.4L9.5 13Z" />
  {:else if name === 'research'}
    <circle cx="12" cy="12" r="2" /><ellipse cx="12" cy="12" rx="9" ry="3.7" /><ellipse cx="12" cy="12" rx="9" ry="3.7" transform="rotate(60 12 12)" /><ellipse cx="12" cy="12" rx="9" ry="3.7" transform="rotate(120 12 12)" />
  {:else if name === 'diplomacy'}
    <path d="m4 9 4-3 4 3 4-3 4 3" /><path d="m4 9 4 7 4-2 4 2 4-7" /><path d="m8 10 4 4 4-4" />
  {:else if name === 'report'}
    <path d="M6 3h9l3 3v15H6z" /><path d="M15 3v4h4" /><path d="M9 11h6M9 15h6M9 19h4" />
  {:else if name === 'settings'}
    <circle cx="12" cy="12" r="3" /><path d="M19 13.5v-3l-2-.6a7 7 0 0 0-.8-1.8l1-1.9-2.2-2.1-1.8 1A7 7 0 0 0 11 4.5L10.5 2h-3L7 4.5a7 7 0 0 0-1.8.8l-2-1-2 2.1 1 1.9a7 7 0 0 0-.8 1.8L-1 10.5v3l2.4.6a7 7 0 0 0 .8 1.8l-1 1.9 2.1 2.1 1.9-1a7 7 0 0 0 1.8.8l.5 2.3h3l.6-2.3a7 7 0 0 0 1.8-.8l1.8 1 2.2-2.1-1-1.9a7 7 0 0 0 .8-1.8z" transform="translate(2.5 0) scale(.8)" />
  {:else if name === 'power'}
    <path d="M12 2v9" /><path d="M7 5.5a8 8 0 1 0 10 0" />
  {:else if name === 'play'}
    <path d="m8 5 11 7-11 7z" />
  {:else if name === 'load'}
    <path d="M5 4h12l2 2v14H5z" /><path d="M8 4v6h7V4M9 17h6" />
  {:else if name === 'user'}
    <circle cx="12" cy="8" r="3.5" /><path d="M5 21c.7-4.1 3.1-6.2 7-6.2s6.3 2.1 7 6.2" />
  {:else if name === 'key'}
    <circle cx="8" cy="15" r="4" /><path d="m11 12 8-8M16 7l2 2M14 9l2 2" />
  {:else if name === 'shield'}
    <path d="m12 2 8 3v6c0 5-3.3 8.6-8 11-4.7-2.4-8-6-8-11V5z" /><path d="m8.5 12 2.2 2.2 4.8-5" />
  {:else if name === 'industry'}
    <path d="M4 20V9l5 3V8l5 3V4h6v16z" /><path d="M8 16h1M13 16h1M17 9h1" />
  {:else if name === 'energy'}
    <path d="m13 2-7 12h6l-1 8 7-12h-6z" />
  {:else if name === 'calendar'}
    <rect x="3" y="5" width="18" height="16" rx="2" /><path d="M7 2v6M17 2v6M3 10h18" />
  {:else if name === 'target'}
    <circle cx="12" cy="12" r="7" /><circle cx="12" cy="12" r="2" /><path d="M12 2v3M12 19v3M2 12h3M19 12h3" />
  {:else if name === 'build'}
    <path d="m14 5 5 5-9 9H5v-5z" /><path d="m12 7 5 5M4 20h16" />
  {:else if name === 'colonize'}
    <circle cx="10" cy="13" r="6" /><path d="M15 5h6M18 2v6M7 10l6 6" />
  {:else if name === 'close'}
    <path d="m5 5 14 14M19 5 5 19" />
  {:else if name === 'menu'}
    <path d="M4 7h16M4 12h16M4 17h16" />
  {:else if name === 'chevron-left'}
    <path d="m15 18-6-6 6-6" />
  {:else if name === 'chevron-right'}
    <path d="m9 18 6-6-6-6" />
  {:else if name === 'plus'}
    <path d="M12 5v14M5 12h14" />
  {:else if name === 'minus'}
    <path d="M5 12h14" />
  {:else if name === 'layers'}
    <path d="m12 3 9 5-9 5-9-5z" /><path d="m3 12 9 5 9-5M3 16l9 5 9-5" />
  {:else if name === 'edit'}
    <path d="M4 20h4L19 9l-4-4L4 16z" /><path d="m13 7 4 4" />
  {:else}
    <circle cx="12" cy="12" r="8" /><path d="M12 8v5M12 17h.01" />
  {/if}
</svg>

<style>
  .icon { display: block; flex: 0 0 auto; }
</style>
