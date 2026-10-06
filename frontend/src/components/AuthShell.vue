<template>
  <main class="app-frame">
    <div class="top-rule" aria-hidden="true"></div>

    <section class="auth-stage" aria-live="polite">
      <div class="auth-panel">
        <header class="brand-lockup">
          <span class="brand-mark" aria-hidden="true">I</span>
          <div>
            <p class="brand-name">Infra<span>Track</span></p>
            <p class="brand-subtitle">Municipal infrastructure management</p>
          </div>
        </header>

        <slot />
      </div>
    </section>

    <footer class="page-footer">
      <span>InfraTrack</span>
      <span>Staff access</span>
    </footer>
  </main>
</template>