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
        var blurThreshold = Number(opts.blurThreshold || 16);
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
                if (score < blurThreshold) {
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
                setAlert("Capture is clear. You can now use this image.", "success");
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
        var startCameraBtn = getEl(opts.startCameraBtnId || "startCameraBtn");
        var captureBtn = getEl(opts.captureBtnId || "captureBtn");
        var retakeBtn = getEl(opts.retakeBtnId || "retakeBtn");

        var blurThresholdCapture = Number(opts.blurThresholdCapture || 16);
        var blurThresholdCrop = Number(opts.blurThresholdCrop || 14);
        var stream = null;
        var cropper = null;
        var currentMode = "upload";
        var initialPreview = previewFrame ? previewFrame.innerHTML : "";

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
            hideElement(photoSourceShell);
            hideElement(cropBtn);
            hideElement(clearSourceBtn);
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

        async function startCamera() {
            stopCamera();
            setStatus("Requesting camera permission...", "info");
            try {
                stream = await window.SE_CAPTURE.requestCamera(cameraVideo, {
                    video: { facingMode: "user", width: { ideal: 1080 }, height: { ideal: 1080 } },
                    audio: false
                });
                showElement(cameraVideo);
                hideElement(cameraPlaceholder);
                showElement(cameraGuides);
                if (captureBtn) {
                    captureBtn.disabled = false;
                }
                if (retakeBtn) {
                    retakeBtn.disabled = true;
                }
                setStatus("Camera ready. Keep your face centered, then tap \"Capture Photo\".", "success");
            } catch (error) {
                setStatus("Unable to access camera. Allow permission or switch to Upload.", "error");
            }
        }

        function buildCropper(dataUrl) {
            cleanupCropper();
            if (!source) {
                return;
            }
            source.src = dataUrl;
            showElement(photoSourceShell);
            showElement(source);
            showElement(cropBtn);
            showElement(clearSourceBtn);
            cropper = new Cropper(source, {
                aspectRatio: 1,
                viewMode: 1,
                autoCropArea: 1,
                responsive: true,
                dragMode: "move",
                background: false
            });
            setStatus("Adjust crop, then tap \"Apply Photo\".", "info");
        }

        function setMode(mode) {
            var safeMode = mode === "camera" ? "camera" : "upload";
            currentMode = safeMode;
            modeButtons.forEach(function (btn) {
                var btnMode = btn.getAttribute("data-photo-mode");
                var isActive = btnMode === safeMode;
                btn.classList.toggle("active", isActive);
                btn.setAttribute("aria-pressed", isActive ? "true" : "false");
            });
            if (safeMode === "camera") {
                hideElement(uploadPanel);
                showElement(cameraPanel);
                startCamera();
                return;
            }
            showElement(uploadPanel);
            hideElement(cameraPanel);
            stopCamera();
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
                if (photoBase64) {
                    photoBase64.value = "";
                }
                var reader = new FileReader();
                reader.onload = function (e) {
                    buildCropper(String(e.target.result || ""));
                };
                reader.readAsDataURL(file);
            });
        }

        if (startCameraBtn) {
            startCameraBtn.addEventListener("click", function () {
                if (currentMode !== "camera") {
                    setMode("camera");
                    return;
                }
                startCamera();
            });
        }

        if (captureBtn) {
            captureBtn.addEventListener("click", function () {
                if (!stream || !cameraVideo || !cameraVideo.videoWidth || !cameraVideo.videoHeight || !cameraCanvas) {
                    setStatus("Camera feed is not ready yet.", "error");
                    return;
                }
                var width = cameraVideo.videoWidth;
                var height = cameraVideo.videoHeight;
                cameraCanvas.width = width;
                cameraCanvas.height = height;
                var ctx = cameraCanvas.getContext("2d", { willReadFrequently: true });
                if (!ctx) {
                    setStatus("Failed to process camera image. Try again.", "error");
                    return;
                }
                ctx.drawImage(cameraVideo, 0, 0, width, height);
                var score = window.SE_CAPTURE.blurScoreFromCanvas(cameraCanvas);
                if (score < blurThresholdCapture) {
                    setStatus("Capture is blurry. Hold still and retake.", "error");
                    return;
                }
                buildCropper(cameraCanvas.toDataURL("image/jpeg", 0.95));
                stopCamera();
                if (retakeBtn) {
                    retakeBtn.disabled = false;
                }
                setStatus("Photo captured. Crop it, then tap \"Apply Photo\".", "success");
            });
        }

        if (retakeBtn) {
            retakeBtn.addEventListener("click", function () {
                cleanupCropper();
                if (photoBase64) {
                    photoBase64.value = "";
                }
                setMode("camera");
                setStatus("Ready for retake. Keep your face inside the guide.", "info");
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
                var score = window.SE_CAPTURE.blurScoreFromCanvas(canvas);
                if (score < blurThresholdCrop) {
                    setStatus("Cropped photo is blurry. Retake or choose a clearer image.", "error");
                    return;
                }
                var data = canvas.toDataURL("image/jpeg", 0.92);
                if (photoBase64) {
                    photoBase64.value = data;
                }
                if (previewFrame) {
                    previewFrame.innerHTML = '<img src="' + data + '" alt="2x2 Preview">';
                }
                setStatus("Photo applied. You can now continue to the next step.", "success");
            });
        }

        if (clearSourceBtn) {
            clearSourceBtn.addEventListener("click", function () {
                cleanupCropper();
                if (photoBase64) {
                    photoBase64.value = "";
                }
                if (input) {
                    input.value = "";
                }
                if (previewFrame) {
                    previewFrame.innerHTML = initialPreview;
                }
                if (photoFileName) {
                    photoFileName.textContent = "No file selected yet.";
                }
                if (currentMode === "camera") {
                    startCamera();
                    setStatus("Selection cleared. Capture a new photo when ready.", "info");
                    return;
                }
                setStatus("Selection cleared. Upload or capture a new photo.", "info");
            });
        }

        form.addEventListener("submit", function (event) {
            var hasAppliedCrop = !!(photoBase64 && photoBase64.value.trim() !== "");
            var hasUpload = !!(input && input.files && input.files.length > 0);
            var hasPreviewImage = !!(previewFrame && previewFrame.querySelector("img"));
            var hasPendingSource = !!(source && !source.classList.contains("d-none"));

            if (hasPendingSource && !hasAppliedCrop) {
                event.preventDefault();
                setStatus("Tap \"Apply Photo\" before continuing.", "error");
                return;
            }
            if (!hasAppliedCrop && !hasUpload && !hasPreviewImage) {
                event.preventDefault();
                setStatus("Please upload or capture a photo first.", "error");
            }
        });

        window.addEventListener("beforeunload", function () {
            stopCamera();
        });

        setMode("upload");
        if (previewFrame && previewFrame.querySelector("img")) {
            setStatus("Existing 2x2 photo detected. You can keep it or replace it.", "info");
        } else {
            setStatus("Choose Upload Photo or Use Camera to begin.", "info");
        }
    }

    window.SE_CAPTURE_UI = {
        initDocumentCaptureModal: initDocumentCaptureModal,
        initPhotoCaptureForm: initPhotoCaptureForm
    };
})(window);
