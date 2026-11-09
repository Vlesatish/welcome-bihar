<?php require __DIR__ . '/../layouts/header.php'; ?>

<link rel="stylesheet" href="/pan-processor/assets/app.css?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/pan-processor/assets/app.css') ?: time() ?>">

<style>
    /* Seamless integration inside Welcome Bihar portal layout */
    .wb-pan-processor-wrap {
        margin: -0.5rem -0.5rem 0;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 2px 12px rgba(15, 23, 42, 0.08);
        border: 1px solid #cbd5e1;
        background: #f1f5f9;
    }
    .wb-pan-processor-wrap .scc-app {
        min-height: calc(100vh - 85px);
        font-family: "Nirmala UI", "Segoe UI", "Noto Sans Devanagari", "Inter", system-ui, -apple-system, sans-serif;
    }
    .wb-pan-processor-wrap .scc-workspace {
        grid-template-columns: minmax(390px, 460px) minmax(0, 1fr);
        gap: 14px;
        padding: 14px 16px 24px;
    }
    @media (max-width: 1200px) {
        .wb-pan-processor-wrap .scc-workspace {
            grid-template-columns: 1fr;
        }
    }
    /* Fullscreen mode toggle support */
    .wb-pan-processor-wrap.is-fullscreen {
        position: fixed;
        inset: 0;
        margin: 0;
        border-radius: 0;
        z-index: 2050;
        overflow-y: auto;
    }
    .wb-pan-processor-wrap.is-fullscreen .scc-app {
        min-height: 100vh;
    }
</style>

<div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
    <div class="d-flex align-items-center gap-2">
        <a href="/services/pan-card" class="btn btn-sm btn-outline-secondary rounded-pill px-3 fw-semibold">
            <i class="bi bi-arrow-left me-1"></i> New Pan Card Apply
        </a>
        <a href="/services/pan-card/list" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-semibold">
            <i class="bi bi-card-checklist me-1"></i> Pan Card List
        </a>
    </div>
    <div class="d-flex align-items-center gap-2">
        <button type="button" id="wbToggleFullscreenBtn" class="btn btn-sm btn-dark rounded-pill px-3 fw-semibold shadow-xs" onclick="togglePanProcessorFullscreen()">
            <i class="bi bi-arrows-fullscreen me-1" id="wbFullscreenIcon"></i>
            <span id="wbFullscreenText">Full Screen</span>
        </button>
    </div>
</div>

<div class="wb-pan-processor-wrap" id="wbPanProcessorWrap">
    <div id="root"></div>
</div>

<script>
function togglePanProcessorFullscreen() {
    var wrap = document.getElementById('wbPanProcessorWrap');
    var icon = document.getElementById('wbFullscreenIcon');
    var text = document.getElementById('wbFullscreenText');
    if (!wrap) return;
    var isFull = wrap.classList.toggle('is-fullscreen');
    if (isFull) {
        document.body.style.overflow = 'hidden';
        if (icon) icon.className = 'bi bi-fullscreen-exit me-1';
        if (text) text.textContent = 'Exit Full Screen';
    } else {
        document.body.style.overflow = '';
        if (icon) icon.className = 'bi bi-arrows-fullscreen me-1';
        if (text) text.textContent = 'Full Screen';
    }
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var wrap = document.getElementById('wbPanProcessorWrap');
        if (wrap && wrap.classList.contains('is-fullscreen')) {
            togglePanProcessorFullscreen();
        }
    }
});
</script>
<script type="module" src="/pan-processor/assets/app.js?v=<?= @filemtime(dirname(__DIR__, 3) . '/public/pan-processor/assets/app.js') ?: time() ?>"></script>

<?php require __DIR__ . '/../layouts/footer.php'; ?>
