(function (window) {
    "use strict";

    function getEl(id) {
        return document.getElementById(id);
    }

    function showElement(element) {
        if (element) {
            element.classList.remove("d-none");
        }
    }

    function hideElement(element) {
        if (element) {
            element.classList.add("d-none");
        }
    }

    function initDocumentCaptureModal(options) {
        var opts = options || {};
        var modalEl = getEl(opts.modalId || "docCaptureModal");
        if (!modalEl || typeof bootstrap === "undefined" || !bootstrap.Modal || !window.SE_CAPTURE) {
            return;
        }

        var modal = bootstrap.Modal.getOrCreateInstance(modalEl);
        var openButtons = document.querySelectorAll(opts.openSelector || "[data-doc-capture-open]");
        var clearButtons = document.querySelectorAll(opts.clearSelector || "[data-doc-capture-clear]");
        var reqText = getEl(opts.requirementTextId || "docCaptureRequirementText");
        var videoEl = getEl(opts.videoId || "docCaptureVideo");
        var canvasEl = getEl(opts.canvasId || "docCaptureCanvas");
        var takeBtn = getEl(opts.takeBtnId || "docCaptureTakeBtn");
        var retakeBtn = getEl(opts.retakeBtnId || "docCaptureRetakeBtn");
        var useBtn = getEl(opts.useBtnId || "docCaptureUseBtn");
        var alertEl = getEl(opts.alertId || "docCaptureAlert");
        var blurThreshold = Number(opts.blurThreshold || 12);
        var hiddenInputPrefix = String(opts.hiddenInputPrefix || "reqCaptureInput");
        var statusPrefix = String(opts.statusPrefix || "reqCaptureStatus");
        var clearBtnPrefix = String(opts.clearBtnPrefix || "reqCaptureClearBtn");
        var fileInputNamePrefix = String(opts.fileInputNamePrefix || "req_");

        var stream = null;
        var currentReqId = "";
        var currentReqName = "";
        var capturedDataUrl = "";

        function setAlert(message, tone) {
            if (!alertEl) {
                return;
            }
            alertEl.textContent = message;
            alertEl.classList.remove("alert-secondary", "alert-success", "alert-danger", "alert-info");
            alertEl.classList.add(
                tone === "success" ? "alert-success"
                    : tone === "danger" ? "alert-danger"
                    : tone === "info" ? "alert-info"
                    : "alert-secondary"
            );
        }

        function getHiddenInput(reqId) {
            return getEl(hiddenInputPrefix + reqId);
        }

        function getStatusEl(reqId) {
            return getEl(statusPrefix + reqId);
        }

        function getClearBtn(reqId) {
            return getEl(clearBtnPrefix + reqId);
        }

        function updateCapturedUi(reqId, hasCapture) {
            var statusEl = getStatusEl(reqId);
            var clearBtn = getClearBtn(reqId);
            if (statusEl) {
                statusEl.textContent = hasCapture
                    ? "Captured photo ready. It will be uploaded when you continue."
                    : "Upload PDF/image or capture a clear photo.";
            }
            if (clearBtn) {
                clearBtn.classList.toggle("d-none", !hasCapture);
            }
        }

        async function startCamera() {
            if (!window.SE_CAPTURE || typeof window.SE_CAPTURE.requestCamera !== "function") {
                setAlert("Camera is not supported on this browser.", "danger");
                return;
            }
            try {
                stream = await window.SE_CAPTURE.requestCamera(videoEl, {
                    video: {
                        facingMode: { ideal: "environment" },
                        width: { ideal: 1280 },
                        height: { ideal: 720 }
                    },
                    audio: false
                });
                setAlert("Camera ready. Keep the document flat and well lit.", "info");
            } catch (error) {
                setAlert("Unable to access camera. Please allow permission or upload file instead.", "danger");
            }
        }

        function stopCamera() {
            if (window.SE_CAPTURE && typeof window.SE_CAPTURE.stopMediaStream === "function") {
                window.SE_CAPTURE.stopMediaStream(stream);
            }
            stream = null;
            if (videoEl) {
                videoEl.srcObject = null;
            }
        }

        openButtons.forEach(function (button) {
            button.addEventListener("click", function () {
                currentReqId = String(button.getAttribute("data-doc-capture-id") || "");
                currentReqName = String(button.getAttribute("data-doc-capture-name") || "Requirement");
                capturedDataUrl = "";
                if (!currentReqId) {
                    return;
                }
                if (reqText) {
                    reqText.textContent = "Requirement: " + currentReqName;
                }
                if (useBtn) {
                    useBtn.disabled = true;
                }
                modal.show();
                startCamera();
            });
        });

        clearButtons.forEach(function (button) {
            button.addEventListener("click", function () {
                var reqId = String(button.getAttribute("data-doc-capture-id") || "");
                var targetInput = getHiddenInput(reqId);
                if (!targetInput) {
                    return;
                }
                targetInput.value = "";
                updateCapturedUi(reqId, false);
            });
        });

        var captureInputs = document.querySelectorAll('input[id^="' + hiddenInputPrefix + '"]');
        captureInputs.forEach(function (hiddenInput) {
            var reqId = String((hiddenInput.id || "").replace(hiddenInputPrefix, ""));
            if (!reqId) {
                return;
            }
            var fileInput = document.querySelector('input[type="file"][name="' + fileInputNamePrefix + reqId + '"]');
            if (!(fileInput instanceof HTMLInputElement)) {
                return;
            }
            fileInput.addEventListener("change", function () {
                if (fileInput.files && fileInput.files.length > 0) {
                    hiddenInput.value = "";
                    updateCapturedUi(reqId, false);
                }
            });
        });

        if (takeBtn) {
            takeBtn.addEventListener("click", function () {
                if (!videoEl || !videoEl.videoWidth || !videoEl.videoHeight || !canvasEl) {
                    setAlert("Camera is not ready yet.", "danger");
                    return;
                }
                canvasEl.width = videoEl.videoWidth;
                canvasEl.height = videoEl.videoHeight;
                var ctx = canvasEl.getContext("2d", { willReadFrequently: true });
                if (!ctx) {
                    setAlert("Failed to process capture.", "danger");
                    return;
                }
                ctx.drawImage(videoEl, 0, 0, canvasEl.width, canvasEl.height);
                var score = window.SE_CAPTURE.blurScoreFromImageData(ctx.getImageData(0, 0, canvasEl.width, canvasEl.height));
                var hardRejectThreshold = Math.max(6, blurThreshold * 0.45);
                if (score < hardRejectThreshold) {
                    if (useBtn) {
                        useBtn.disabled = true;
                    }
                    capturedDataUrl = "";
                    setAlert("Capture is blurry. Retake with better focus and lighting.", "danger");
                    return;
                }
                capturedDataUrl = canvasEl.toDataURL("image/jpeg", 0.92);
                if (useBtn) {
                    useBtn.disabled = false;
                }
                if (score < blurThreshold) {
                    setAlert("Capture looks slightly blurry. Retake is recommended, but you can still use this image.", "info");
                } else {
                    setAlert("Capture is clear. You can now use this image.", "success");
                }
            });
        }

        if (retakeBtn) {
            retakeBtn.addEventListener("click", function () {
                capturedDataUrl = "";
                if (useBtn) {
                    useBtn.disabled = true;
                }
                setAlert("Retake ready. Keep document centered in the guide.", "info");
            });
        }

        if (useBtn) {
            useBtn.addEventListener("click", function () {
                if (!currentReqId || !capturedDataUrl) {
                    return;
                }
                var targetInput = getHiddenInput(currentReqId);
                if (!targetInput) {
                    return;
                }
                targetInput.value = capturedDataUrl;
                updateCapturedUi(currentReqId, true);
                modal.hide();
            });
        }

        modalEl.addEventListener("hidden.bs.modal", function () {
            stopCamera();
            capturedDataUrl = "";
            if (useBtn) {
                useBtn.disabled = true;
            }
            setAlert("Ready to capture.", "secondary");
        });
    }

    function initPhotoCaptureForm(options) {
        var opts = options || {};
        if (!window.SE_CAPTURE) {
            return;
        }

        var form = getEl(opts.formId || "step5Form");
        if (!form || typeof Cropper === "undefined") {
            return;
        }

        var input = getEl(opts.inputId || "photoInput");
        var source = getEl(opts.sourceId || "photoSource");
        var previewFrame = getEl(opts.previewFrameId || "photoPreviewFrame");
        var previewBadge = getEl(opts.previewBadgeId || "photoPreviewBadge");
        var workspace = getEl(opts.workspaceId || "photoWorkspace");
        var cropModalEl = getEl(opts.cropModalId || "photoCropModal");
        var cropModal = cropModalEl && typeof bootstrap !== "undefined" && bootstrap.Modal
            ? bootstrap.Modal.getOrCreateInstance(cropModalEl)
            : null;
        var cropBtn = getEl(opts.cropBtnId || "cropBtn");
        var clearSourceBtn = getEl(opts.clearSourceBtnId || "clearSourceBtn");
        var photoBase64 = getEl(opts.base64InputId || "photoBase64");
        var photoStatusWrap = getEl(opts.statusWrapId || "photoStatusWrap");
        var photoStatusIcon = getEl(opts.statusIconId || "photoStatusIcon");
        var photoStatus = getEl(opts.statusTextId || "photoStatus");
        var photoFileName = getEl(opts.fileNameId || "photoFileName");
        var uploadPanel = getEl(opts.uploadPanelId || "uploadPanel");
        var cameraPanel = getEl(opts.cameraPanelId || "cameraPanel");
        var photoSourceShell = getEl(opts.sourceShellId || "photoSourceShell");
        var modeButtons = document.querySelectorAll(opts.modeButtonsSelector || "[data-photo-mode]");
        var cameraVideo = getEl(opts.videoId || "cameraVideo");
        var cameraCanvas = getEl(opts.canvasId || "cameraCanvas");
        var cameraPlaceholder = getEl(opts.placeholderId || "cameraPlaceholder");
        var cameraGuides = getEl(opts.guidesId || "cameraGuides");
        var fullscreenBtn = getEl(opts.fullscreenBtnId || "toggleFullscreenBtn");
        var captureBtn = getEl(opts.captureBtnId || "captureBtn");
        var submitBtn = getEl(opts.submitBtnId || "step5NextBtn");
        var submitNote = getEl(opts.submitNoteId || "step5SubmitNote");

        var stream = null;
        var cropper = null;
        var currentMode = "upload";
        var initialPreview = previewFrame ? previewFrame.innerHTML : "";
        var overlayFallback = false;
        var submitting = false;
        var pendingSourceData = "";
        var draftSourceActive = false;
        var appliedFromModal = false;
        var retakeRequested = false;

        function hasPreviewImage() {
            return !!(previewFrame && previewFrame.querySelector("img"));
        }

        function syncSubmitState() {
            if (!submitBtn) {
                return;
            }
            var canContinue = !!(photoBase64 && photoBase64.value.trim() !== "") || hasPreviewImage();
            submitBtn.disabled = !canContinue || submitting;
            if (workspace) {
                workspace.classList.toggle("photo-workspace-ready", canContinue);
            }
            if (previewBadge) {
                previewBadge.textContent = canContinue ? "Photo Ready" : "Photo Needed";
                previewBadge.className = canContinue
                    ? "badge rounded-pill text-bg-success-subtle border text-success-emphasis"
                    : "badge rounded-pill text-bg-warning-subtle border text-warning-emphasis";
            }
            if (submitNote) {
                submitNote.textContent = canContinue
                    ? "Photo is ready. You may continue to the next step."
                    : "Apply a cropped photo to continue.";
            }
        }

        function setStatus(message, tone) {
            var safeTone = ["info", "success", "error"].indexOf(tone) >= 0 ? tone : "info";
            var iconMap = {
                info: "fa-circle-info",
                success: "fa-circle-check",
                error: "fa-circle-exclamation"
            };
            if (photoStatusWrap) {
                photoStatusWrap.classList.remove("photo-status-info", "photo-status-success", "photo-status-error");
                photoStatusWrap.classList.add("photo-status-" + safeTone);
            }
            if (photoStatusIcon) {
                photoStatusIcon.innerHTML = '<i class="fa-solid ' + iconMap[safeTone] + '"></i>';
            }
            if (photoStatus) {
                photoStatus.textContent = message;
            }
        }

        function cleanupCropper() {
            if (cropper) {
                cropper.destroy();
                cropper = null;
            }
            if (source) {
                source.removeAttribute("src");
            }
            hideElement(source);
        }

        function stopCamera() {
            window.SE_CAPTURE.stopMediaStream(stream);
            stream = null;
            if (cameraVideo) {
                cameraVideo.pause();
                cameraVideo.srcObject = null;
            }
            showElement(cameraPlaceholder);
            hideElement(cameraVideo);
            hideElement(cameraCanvas);
            hideElement(cameraGuides);
            if (captureBtn) {
                captureBtn.disabled = true;
            }
        }

        function supportsFullscreen() {
            return !!(
                cameraPanel
                && (cameraPanel.requestFullscreen
                    || cameraPanel.webkitRequestFullscreen
                    || document.exitFullscreen
                    || document.webkitExitFullscreen)
            );
        }

        function isFullscreenActive() {
            return document.fullscreenElement === cameraPanel || document.webkitFullscreenElement === cameraPanel;
        }

        function syncFullscreenButton() {
            if (!fullscreenBtn) {
                return;
            }
            var active = isFullscreenActive() || overlayFallback;
            fullscreenBtn.innerHTML = active
                ? '<i class="fa-solid fa-compress me-1"></i>Exit Full Screen'
                : '<i class="fa-solid fa-expand me-1"></i>Full Screen';
        }

        function enterOverlayFallback() {
            if (!cameraPanel) {
                return;
            }
            overlayFallback = true;
            cameraPanel.classList.add("photo-panel-overlay");
            document.body.classList.add("photo-camera-overlay-open");
            syncFullscreenButton();
        }

        function exitOverlayFallback() {
            if (!cameraPanel) {
                return;
            }
            overlayFallback = false;
            cameraPanel.classList.remove("photo-panel-overlay");
            document.body.classList.remove("photo-camera-overlay-open");
            syncFullscreenButton();
        }

        function toggleFullscreenMode() {
            if (!cameraPanel) {
                return;
            }

            if (supportsFullscreen()) {
                if (isFullscreenActive()) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    }
                } else if (cameraPanel.requestFullscreen) {
                    cameraPanel.requestFullscreen();
                } else if (cameraPanel.webkitRequestFullscreen) {
                    cameraPanel.webkitRequestFullscreen();
                }
                return;
            }

            if (overlayFallback) {
                exitOverlayFallback();
            } else {
                enterOverlayFallback();
            }
        }

        async function startCamera() {
            stopCamera();
            setStatus("Requesting camera permission...", "info");
            try {
                stream = await window.SE_CAPTURE.requestCamera(cameraVideo, {
                    video: { facingMode: "user", width: { ideal: 1080 }, height: { ideal: 1080 } },
                    audio: false
                });
                try {
                    var tracks = stream.getVideoTracks ? stream.getVideoTracks() : [];
                    if (tracks.length > 0 && tracks[0].getCapabilities && tracks[0].applyConstraints) {
                        var caps = tracks[0].getCapabilities() || {};
                        if (typeof caps.zoom === "object") {
                            tracks[0].applyConstraints({ advanced: [{ zoom: 1 }] }).catch(function () {});
                        }
                    }
                } catch (_e) {
                    // Ignore; not all browsers/devices support zoom constraints.
                }
                showElement(cameraVideo);
                hideElement(cameraPlaceholder);
                showElement(cameraGuides);
                if (captureBtn) {
                    captureBtn.disabled = false;
                }
                setStatus("Camera ready. Keep your face centered, then tap \"Capture Photo\".", "success");
            } catch (error) {
                setStatus("Unable to access camera. Allow permission or switch to Upload.", "error");
            }
        }

        function openCropperModal(dataUrl) {
            pendingSourceData = dataUrl;
            draftSourceActive = true;
            appliedFromModal = false;
            retakeRequested = false;
            if (cropModal) {
                cropModal.show();
                return;
            }
            buildCropper();
        }

        function buildCropper() {
            cleanupCropper();
            if (!source) {
                return;
            }
            if (!pendingSourceData) {
                return;
            }
            source.onload = function () {
                source.onload = null;
                cropper = new Cropper(source, {
                    aspectRatio: 1,
                    viewMode: 1,
                    autoCropArea: 1,
                    responsive: true,
                    dragMode: "move",
                    background: false
                });
            };
            source.src = pendingSourceData;
            showElement(source);
            setStatus("Adjust crop, then tap \"Apply Photo\".", "info");
        }

        function setMode(mode) {
            var safeMode = mode === "camera" ? "camera" : "upload";
            currentMode = safeMode;
            if (workspace) {
                workspace.setAttribute("data-photo-mode", safeMode);
            }
            modeButtons.forEach(function (btn) {
                var btnMode = btn.getAttribute("data-photo-mode");
                var isActive = btnMode === safeMode;
                btn.classList.toggle("active", isActive);
                btn.setAttribute("aria-pressed", isActive ? "true" : "false");
            });
            if (safeMode === "camera") {
                hideElement(uploadPanel);
                showElement(cameraPanel);
                if (cameraPanel) {
                    cameraPanel.classList.add("is-active");
                }
                if (uploadPanel) {
                    uploadPanel.classList.remove("is-active");
                }
                startCamera();
                syncFullscreenButton();
                return;
            }
            showElement(uploadPanel);
            hideElement(cameraPanel);
            if (uploadPanel) {
                uploadPanel.classList.add("is-active");
            }
            if (cameraPanel) {
                cameraPanel.classList.remove("is-active");
            }
            stopCamera();
            if (overlayFallback) {
                exitOverlayFallback();
            }
            setStatus("Upload a clear photo, crop it, then tap \"Apply Photo\".", "info");
        }

        modeButtons.forEach(function (btn) {
            btn.addEventListener("click", function () {
                setMode(btn.getAttribute("data-photo-mode") || "upload");
            });
        });

        if (input) {
            input.addEventListener("change", function (event) {
                var file = event.target.files && event.target.files[0];
                if (photoFileName) {
                    photoFileName.textContent = file ? ("Selected: " + file.name) : "No file selected yet.";
                }
                if (!file) {
                    return;
                }
                var reader = new FileReader();
                reader.onload = function (e) {
                    openCropperModal(String(e.target.result || ""));
                };
                reader.readAsDataURL(file);
            });
        }

        if (fullscreenBtn) {
            fullscreenBtn.addEventListener("click", function () {
                toggleFullscreenMode();
            });
        }

        if (captureBtn) {
            captureBtn.addEventListener("click", function () {
                if (!stream || !cameraVideo || !cameraVideo.videoWidth || !cameraVideo.videoHeight || !cameraCanvas) {
                    setStatus("Camera feed is not ready yet.", "error");
                    return;
                }
                var srcWidth = cameraVideo.videoWidth;
                var srcHeight = cameraVideo.videoHeight;
                // Match the visible square preview framing to avoid vertical jump after capture.
                var outputSize = Math.min(srcWidth, srcHeight);
                cameraCanvas.width = outputSize;
                cameraCanvas.height = outputSize;
                var ctx = cameraCanvas.getContext("2d", { willReadFrequently: true });
                if (!ctx) {
                    setStatus("Failed to process camera image. Try again.", "error");
                    return;
                }
                var srcAspect = srcWidth / srcHeight;
                var dstAspect = 1;
                var drawWidth;
                var drawHeight;
                var offsetX;
                var offsetY;
                if (srcAspect > dstAspect) {
                    drawHeight = srcHeight;
                    drawWidth = drawHeight * dstAspect;
                    offsetX = (srcWidth - drawWidth) / 2;
                    offsetY = 0;
                } else {
                    drawWidth = srcWidth;
                    drawHeight = drawWidth / dstAspect;
                    offsetX = 0;
                    offsetY = (srcHeight - drawHeight) / 2;
                }
                // Keep front-camera capture framing consistent with mirrored live preview.
                ctx.save();
                ctx.translate(outputSize, 0);
                ctx.scale(-1, 1);
                ctx.drawImage(
                    cameraVideo,
                    offsetX,
                    offsetY,
                    drawWidth,
                    drawHeight,
                    0,
                    0,
                    outputSize,
                    outputSize
                );
                ctx.restore();
                pendingSourceData = cameraCanvas.toDataURL("image/jpeg", 0.95);
                stopCamera();
                hideElement(cameraPanel);
                if (overlayFallback) {
                    exitOverlayFallback();
                }
                if (isFullscreenActive()) {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    }
                }
                setStatus("Photo captured. Adjust the crop, then confirm the photo.", "success");
                openCropperModal(pendingSourceData);
            });
        }

        if (cropBtn) {
            cropBtn.addEventListener("click", function () {
                if (!cropper) {
                    setStatus("Please select or capture a photo first.", "error");
                    return;
                }
                var canvas = cropper.getCroppedCanvas({ width: 512, height: 512 });
                if (!canvas) {
                    setStatus("Unable to crop this image. Please try another photo.", "error");
                    return;
                }
                var data = canvas.toDataURL("image/jpeg", 0.92);
                if (photoBase64) {
                    photoBase64.value = data;
                }
                if (input) {
                    input.value = "";
                }
                if (photoFileName) {
                    photoFileName.textContent = "Cropped photo is ready.";
                }
                if (previewFrame) {
                    previewFrame.innerHTML = '<img src="' + data + '" alt="2x2 Preview">';
                }
                appliedFromModal = true;
                draftSourceActive = false;
                setStatus("Photo applied. You can now continue to the next step.", "success");
                syncSubmitState();
                if (cropModal) {
                    cropModal.hide();
                }
            });
        }

        if (clearSourceBtn) {
            clearSourceBtn.addEventListener("click", function () {
                cleanupCropper();
                retakeRequested = true;
                draftSourceActive = false;
                pendingSourceData = "";
                if (cropModal) {
                    cropModal.hide();
                    return;
                }
                if (input) {
                    input.value = "";
                }
                if (photoFileName) {
                    photoFileName.textContent = "No file selected yet.";
                }
                if (currentMode === "camera") {
                    showElement(cameraPanel);
                    startCamera();
                    setStatus("Selection cleared. Capture a new photo when ready.", "info");
                } else {
                    setStatus("Selection cleared. Upload a new photo when ready.", "info");
                }
                syncSubmitState();
            });
        }

        form.addEventListener("submit", function (event) {
            if (submitting) {
                event.preventDefault();
                return;
            }
            var hasAppliedCrop = !!(photoBase64 && photoBase64.value.trim() !== "");
            var hasUpload = !!(input && input.files && input.files.length > 0);
            var hasPreview = hasPreviewImage();
            var hasPendingSource = !!(source && !source.classList.contains("d-none"));

            if (hasPendingSource && !hasAppliedCrop) {
                event.preventDefault();
                setStatus("Tap \"Apply Photo\" before continuing.", "error");
                return;
            }
            if (!hasAppliedCrop && !hasUpload && !hasPreview) {
                event.preventDefault();
                setStatus("Please upload or capture a photo first.", "error");
                syncSubmitState();
                return;
            }
            submitting = true;
            if (submitBtn) {
                submitBtn.disabled = true;
                var nextLabel = submitBtn.querySelector(".step5-next-label");
                var loadingLabel = submitBtn.querySelector(".step5-loading-label");
                if (nextLabel) {
                    nextLabel.classList.add("d-none");
                }
                if (loadingLabel) {
                    loadingLabel.classList.remove("d-none");
                }
            }
            if (submitNote) {
                submitNote.textContent = "Please wait while we save your photo...";
            }
        });

        window.addEventListener("beforeunload", function () {
            if (overlayFallback) {
                exitOverlayFallback();
            }
            stopCamera();
        });

        document.addEventListener("fullscreenchange", syncFullscreenButton);
        document.addEventListener("webkitfullscreenchange", syncFullscreenButton);

        if (cropModalEl) {
            cropModalEl.addEventListener("shown.bs.modal", function () {
                buildCropper();
            });
            cropModalEl.addEventListener("hidden.bs.modal", function () {
                cleanupCropper();
                pendingSourceData = "";
                if (appliedFromModal) {
                    appliedFromModal = false;
                    retakeRequested = false;
                    return;
                }
                if (input) {
                    input.value = "";
                }
                if (photoFileName && currentMode === "upload") {
                    photoFileName.textContent = "No file selected yet.";
                }
                if (retakeRequested) {
                    retakeRequested = false;
                    if (currentMode === "camera") {
                        showElement(cameraPanel);
                        startCamera();
                        setStatus("Selection cleared. Capture a new photo when ready.", "info");
                    } else {
                        setStatus("Selection cleared. Upload a new photo when ready.", "info");
                    }
                    syncSubmitState();
                    return;
                }
                if (currentMode === "camera") {
                    showElement(cameraPanel);
                    startCamera();
                    setStatus("Crop closed. Capture another photo or switch source.", "info");
                } else {
                    setStatus("Crop closed. Choose another photo when ready.", "info");
                }
                draftSourceActive = false;
                syncSubmitState();
            });
        }

        setMode("upload");
        if (hasPreviewImage()) {
            setStatus("Existing 2x2 photo detected. You can keep it or replace it.", "info");
        } else {
            setStatus("Choose Upload Photo or Use Camera to begin.", "info");
        }
        syncSubmitState();
    }

    window.SE_CAPTURE_UI = {
        initDocumentCaptureModal: initDocumentCaptureModal,
        initPhotoCaptureForm: initPhotoCaptureForm
    };
})(window);
