<script lang="ts">
  export let compact = false;
  export let subtitle = true;
</script>

<div class:compact class="brand">
  <svg class="mark" viewBox="0 0 100 100" aria-hidden="true">
    <defs>
      <linearGradient id="starFill" x1="0" x2="1" y1="0" y2="1">
        <stop offset="0" stop-color="#f5fbff" />
        <stop offset=".48" stop-color="#56c7ff" />
        <stop offset="1" stop-color="#b9eaff" />
      </linearGradient>
    </defs>
    <circle cx="50" cy="50" r="37" fill="none" stroke="currentColor" stroke-opacity=".35" stroke-width="2" stroke-dasharray="8 7" />
    <circle cx="50" cy="50" r="26" fill="none" stroke="#d6a94f" stroke-opacity=".65" stroke-width="1.5" />
    <path d="M50 5 59 40 95 50 59 60 50 95 41 60 5 50 41 40Z" fill="url(#starFill)" stroke="#eaf8ff" stroke-width="1.5" />
    <path d="m50 28 7 15 15 7-15 7-7 15-7-15-15-7 15-7Z" fill="#06111f" stroke="#9fe0ff" stroke-width="1.5" />
    <circle cx="50" cy="50" r="5" fill="#f6fbff" />
  </svg>
  <div class="wording">
    <div class="title">STARS REKINDLED</div>
    {#if subtitle}<div class="subtitle">EXPLORE · EXPAND · ENDURE</div>{/if}
  </div>
</div>

<style>
  .brand { display: flex; align-items: center; gap: 1rem; color: #bde9ff; }
  .mark { width: 76px; height: 76px; filter: drop-shadow(0 0 18px rgba(52,190,255,.36)); }
  .title { color: #edf9ff; font-size: clamp(1.45rem, 2.5vw, 2.65rem); font-weight: 500; letter-spacing: .28em; white-space: nowrap; text-shadow: 0 0 20px rgba(94,201,255,.36); }
  .subtitle { margin-top: .42rem; color: #cda55b; font-size: .72rem; letter-spacing: .38em; }
  .compact { gap: .65rem; }
  .compact .mark { width: 40px; height: 40px; }
  .compact .title { font-size: 1rem; letter-spacing: .22em; }
  .compact .subtitle { display: none; }
  @media (max-width: 700px) {
    .brand:not(.compact) { flex-direction: column; gap: .2rem; text-align: center; }
    .brand:not(.compact) .mark { width: 64px; height: 64px; }
    .brand:not(.compact) .title { font-size: 1.4rem; letter-spacing: .18em; }
    .subtitle { letter-spacing: .22em; }
  }
</style>
