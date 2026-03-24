<?php extract($printable, EXTR_SKIP); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Form Preview</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Sora:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo htmlspecialchars(asset_url('css/style.css')); ?>">
    <link rel="icon" type="image/png" href="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars(asset_url('images/lgu-logo.png')); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
    (function () {
        try {
            var savedTheme = localStorage.getItem('app-theme');
            var prefersDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
            var theme = savedTheme || (prefersDark ? 'dark' : 'light');
            document.documentElement.setAttribute('data-theme', theme);
            document.documentElement.style.colorScheme = theme;
        } catch (error) {
            document.documentElement.setAttribute('data-theme', 'light');
            document.documentElement.style.colorScheme = 'light';
        }
    })();
    </script>
</head>
<body class="bg-light">

<style>
:root {
    --print-page-width: 8.5in;
    --print-page-height: 13in;
    --preview-scale: 1;
}

.print-toolbar {
    position: sticky;
    top: 0;
    z-index: 20;
}

.print-preview-shell {
    width: 100%;
    max-width: none;
    margin: 0;
}

.preview-meta {
    font-size: 0.82rem;
    color: #5f7380;
}

.preview-surface {
    border: 1px solid #cddce8;
    border-radius: 12px;
    padding: 0.35rem;
    background: linear-gradient(180deg, #f7fbff 0%, #f2f7fc 100%);
    overflow: auto;
    height: calc(100vh - 210px);
    height: calc(100dvh - 210px);
    touch-action: pan-x pan-y;
    overscroll-behavior: contain;
    position: relative;
    display: flex;
    justify-content: center;
    align-items: flex-start;
}

.print-toolbar-controls {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex-wrap: wrap;
}

.print-toolbar .btn {
    font-size: 0.84rem;
}

.print-toolbar .btn-group .btn {
    min-width: 34px;
    min-height: 34px;
    padding: 0.32rem 0.52rem;
}

.paper-scale-wrap {
    width: calc(var(--print-page-width) * var(--preview-scale));
    min-height: calc(var(--print-page-height) * var(--preview-scale));
    margin: 0 auto;
    flex: 0 0 auto;
}

.preview-surface .application-paper {
    margin: 0;
    border: 1px solid #b7c9d8;
    box-shadow: 0 14px 30px rgba(24, 74, 111, 0.18);
    transform: scale(var(--preview-scale));
    transform-origin: top left;
}

.preview-fab-zoom {
    position: sticky;
    left: calc(100% - 56px);
    bottom: 12px;
    z-index: 25;
    display: inline-flex;
    flex-direction: column;
    gap: 0.4rem;
    margin-top: -96px;
}

.preview-fab-zoom .btn {
    width: 42px;
    height: 42px;
    border-radius: 999px;
    box-shadow: 0 8px 18px rgba(18, 55, 79, 0.22);
    padding: 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

@media (max-width: 991.98px) {
    .preview-surface {
        height: calc(100vh - 250px);
        height: calc(100dvh - 250px);
    }
}

@media (max-width: 575.98px) {
    .print-toolbar-controls {
        width: 100%;
        overflow-x: auto;
        white-space: nowrap;
        flex-wrap: nowrap;
        padding-bottom: 0.2rem;
    }

    .print-toolbar-controls .btn,
    .print-toolbar-controls .btn-group {
        flex: 0 0 auto;
    }

    .preview-surface {
        height: calc(100vh - 280px);
        height: calc(100dvh - 280px);
    }
}

@media print {
    .app-page-header,
    .print-toolbar,
    .preview-meta,
    .preview-fab-zoom,
    .sidebar,
    .navbar {
        display: none !important;
    }

    .preview-surface {
        border: none !important;
        padding: 0 !important;
        background: transparent !important;
        overflow: visible !important;
        height: auto !important;
    }

    .paper-scale-wrap {
        width: auto !important;
        min-height: auto !important;
        margin: 0 !important;
    }

    .preview-surface .application-paper {
        border: none !important;
        box-shadow: none !important;
        transform: none !important;
    }

    main {
        padding: 0 !important;
    }
}
</style>

<div class="container-fluid bg-light" style="min-height: 100vh;">
    <main class="px-3 px-md-4 py-4 print-preview-shell">
            <section class="app-page-header">
                <div>
                    <span class="app-page-eyebrow">Printable Form</span>
                    <h1 class="app-page-title"><i class="fa-solid fa-file-lines me-2 text-primary"></i>Application Form Preview</h1>
                    <p class="app-page-subtitle">Review the old-style printable form, zoom in if needed, then print or save it.</p>
                </div>
            </section>

            <div class="print-toolbar d-flex justify-content-between align-items-center flex-wrap gap-2 bg-white border rounded p-2 mb-2">
                <div class="small text-wrap">
                    Application No: <strong><?php echo htmlspecialchars((string) ($applicationNumber ?? '')); ?></strong>
                </div>
                <div class="print-toolbar-controls">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Preview zoom controls">
                        <button type="button" class="btn btn-outline-secondary" id="zoomOutBtn" title="Zoom out">
                            <i class="fa-solid fa-magnifying-glass-minus"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="fitWidthBtn" title="Fit to screen">
                            Fit
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="zoomResetBtn" title="Reset zoom">
                            100%
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="zoomInBtn" title="Zoom in">
                            <i class="fa-solid fa-magnifying-glass-plus"></i>
                        </button>
                    </div>
                    <span class="badge text-bg-light" id="previewScaleLabel">100%</span>
                    <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn btn-outline-secondary btn-sm" id="printBackBtn">
                        <i class="fa-solid fa-arrow-left me-1"></i>Back
                    </a>
                    <a href="<?php echo htmlspecialchars($downloadUrl); ?>" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                        <i class="fa-solid fa-download me-1"></i>Download PDF
                    </a>
                    <button type="button" class="btn btn-primary btn-sm" id="printNowBtn">
                        <i class="fa-solid fa-print me-1"></i>Print / Save PDF
                    </button>
                </div>
            </div>

            <p class="preview-meta mb-2">
                Mobile tip: use <strong>Fit</strong> for full-page preview, then zoom in for details before printing.
            </p>

            <div class="preview-surface" id="previewSurface">
                <div class="paper-scale-wrap" id="paperScaleWrap">
                    <?php require __DIR__ . '/application_form_pdf.php'; ?>
                </div>
            </div>

            <div class="preview-fab-zoom d-lg-none" aria-label="Mobile zoom controls">
                <button type="button" class="btn btn-primary btn-sm" id="fabZoomInBtn" title="Zoom in">
                    <i class="fa-solid fa-plus"></i>
                </button>
                <button type="button" class="btn btn-outline-primary btn-sm" id="fabZoomOutBtn" title="Zoom out">
                    <i class="fa-solid fa-minus"></i>
                </button>
            </div>
    </main>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const surface = document.getElementById('previewSurface');
    const paper = surface ? surface.querySelector('.application-paper') : null;
    const scaleWrap = document.getElementById('paperScaleWrap');
    const zoomOutBtn = document.getElementById('zoomOutBtn');
    const zoomInBtn = document.getElementById('zoomInBtn');
    const zoomResetBtn = document.getElementById('zoomResetBtn');
    const fitWidthBtn = document.getElementById('fitWidthBtn');
    const fabZoomInBtn = document.getElementById('fabZoomInBtn');
    const fabZoomOutBtn = document.getElementById('fabZoomOutBtn');
    const scaleLabel = document.getElementById('previewScaleLabel');
    const printNowBtn = document.getElementById('printNowBtn');

    if (!surface || !paper || !scaleWrap) {
        return;
    }

    const minScale = 0.55;
    const maxScale = 2.2;
    const step = 0.05;
    let scale = 1;
    let fitMode = true;
    let isPanning = false;
    let panStartX = 0;
    let panStartY = 0;
    let panScrollLeft = 0;
    let panScrollTop = 0;

    const syncScaledSurfaceSize = function () {
        scaleWrap.style.width = (paper.offsetWidth * scale) + 'px';
        scaleWrap.style.minHeight = (paper.offsetHeight * scale) + 'px';
    };

    const setScale = function (nextScale) {
        scale = Math.max(minScale, Math.min(maxScale, nextScale));
        document.documentElement.style.setProperty('--preview-scale', String(scale));
        surface.style.touchAction = scale > 1 ? 'none' : 'pan-x pan-y';
        syncScaledSurfaceSize();

        if (scaleLabel) {
            scaleLabel.textContent = Math.round(scale * 100) + '%';
        }
        if (zoomOutBtn) {
            zoomOutBtn.disabled = scale <= minScale + 0.001;
        }
        if (zoomInBtn) {
            zoomInBtn.disabled = scale >= maxScale - 0.001;
        }
    };

    const fitToWidth = function () {
        const paperWidth = paper.offsetWidth || 816;
        const available = Math.max(320, surface.clientWidth - 8);
        setScale(available / paperWidth);
    };

    let pinchStartDistance = 0;
    let pinchStartScale = 1;

    const touchDistance = function (a, b) {
        const dx = a.clientX - b.clientX;
        const dy = a.clientY - b.clientY;
        return Math.sqrt((dx * dx) + (dy * dy));
    };

    const touchMidpoint = function (a, b) {
        return { x: (a.clientX + b.clientX) / 2, y: (a.clientY + b.clientY) / 2 };
    };

    const startPan = function (clientX, clientY) {
        if (scale <= 1) {
            return;
        }

        isPanning = true;
        panStartX = clientX;
        panStartY = clientY;
        panScrollLeft = surface.scrollLeft;
        panScrollTop = surface.scrollTop;
    };

    const movePan = function (clientX, clientY) {
        if (!isPanning || scale <= 1) {
            return;
        }

        const dx = clientX - panStartX;
        const dy = clientY - panStartY;
        surface.scrollLeft = panScrollLeft - dx;
        surface.scrollTop = panScrollTop - dy;
    };

    const endPan = function () {
        isPanning = false;
    };

    if (fitMode) {
        fitToWidth();
    } else {
        setScale(1);
    }
    syncScaledSurfaceSize();

    surface.addEventListener('touchstart', function (event) {
        if (event.touches.length === 2) {
            pinchStartDistance = touchDistance(event.touches[0], event.touches[1]);
            pinchStartScale = scale;
            return;
        }

        if (event.touches.length === 1 && pinchStartDistance === 0) {
            startPan(event.touches[0].clientX, event.touches[0].clientY);
        }
    }, { passive: true });

    surface.addEventListener('touchmove', function (event) {
        if (event.touches.length === 2 && pinchStartDistance > 0) {
            event.preventDefault();
            fitMode = false;

            const currentDistance = touchDistance(event.touches[0], event.touches[1]);
            const oldScale = scale;
            const nextScale = pinchStartScale * (currentDistance / pinchStartDistance);
            const mid = touchMidpoint(event.touches[0], event.touches[1]);
            const rect = surface.getBoundingClientRect();
            const focusX = (mid.x - rect.left) + surface.scrollLeft;
            const focusY = (mid.y - rect.top) + surface.scrollTop;

            setScale(nextScale);

            const ratio = oldScale > 0 ? (scale / oldScale) : 1;
            surface.scrollLeft = (focusX * ratio) - (mid.x - rect.left);
            surface.scrollTop = (focusY * ratio) - (mid.y - rect.top);
            return;
        }

        if (event.touches.length === 1 && isPanning) {
            event.preventDefault();
            movePan(event.touches[0].clientX, event.touches[0].clientY);
        }
    }, { passive: false });

    surface.addEventListener('touchend', function (event) {
        if (event.touches.length < 2) {
            pinchStartDistance = 0;
        }
        if (event.touches.length === 0) {
            endPan();
        }
    }, { passive: true });

    surface.addEventListener('mousedown', function (event) {
        if (scale <= 1) {
            return;
        }

        event.preventDefault();
        startPan(event.clientX, event.clientY);
    });

    window.addEventListener('mousemove', function (event) {
        movePan(event.clientX, event.clientY);
    });

    window.addEventListener('mouseup', endPan);

    if (zoomOutBtn) {
        zoomOutBtn.addEventListener('click', function () {
            fitMode = false;
            setScale(scale - step);
        });
    }

    if (fabZoomOutBtn) {
        fabZoomOutBtn.addEventListener('click', function () {
            fitMode = false;
            setScale(scale - step);
        });
    }

    if (zoomInBtn) {
        zoomInBtn.addEventListener('click', function () {
            fitMode = false;
            setScale(scale + step);
        });
    }

    if (fabZoomInBtn) {
        fabZoomInBtn.addEventListener('click', function () {
            fitMode = false;
            setScale(scale + step);
        });
    }

    if (zoomResetBtn) {
        zoomResetBtn.addEventListener('click', function () {
            fitMode = false;
            setScale(1);
        });
    }

    if (fitWidthBtn) {
        fitWidthBtn.addEventListener('click', function () {
            fitMode = true;
            fitToWidth();
        });
    }

    if (printNowBtn) {
        printNowBtn.addEventListener('click', function () {
            window.print();
        });
    }

    window.addEventListener('resize', function () {
        if (fitMode) {
            fitToWidth();
        } else {
            syncScaledSurfaceSize();
        }
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
