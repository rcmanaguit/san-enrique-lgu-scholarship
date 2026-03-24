document.addEventListener("DOMContentLoaded", () => {
    const canvas = document.getElementById("signature-canvas");
    const modalCanvas = document.getElementById("signature-modal-canvas");
    const clearBtn = document.getElementById("clear-signature-btn");
    const clearModalBtn = document.getElementById("clear-signature-modal-btn");
    const openModalBtn = document.getElementById("open-signature-modal-btn");
    const saveModalBtn = document.getElementById("save-signature-modal-btn");
    const hiddenSigInput = document.getElementById("e_signature_base64");
    const signatureUploadInput = document.getElementById("signature_upload");
    const signatureSourceNote = document.getElementById("signature-source-note");
    const applicationForm = document.getElementById("student-application-form");
    const signatureModalElement = document.getElementById("signature-modal");

    if (
        !canvas ||
        !modalCanvas ||
        !clearBtn ||
        !clearModalBtn ||
        !openModalBtn ||
        !saveModalBtn ||
        !hiddenSigInput ||
        !signatureUploadInput ||
        !applicationForm ||
        !signatureModalElement ||
        typeof SignaturePad === "undefined"
    ) {
        return;
    }

    const signatureModal = window.bootstrap?.Modal ? new window.bootstrap.Modal(signatureModalElement) : null;
    const signaturePad = new SignaturePad(canvas, {
        penColor: "rgb(0, 0, 0)",
        backgroundColor: "rgba(0, 0, 0, 0)",
    });
    const modalSignaturePad = new SignaturePad(modalCanvas, {
        penColor: "rgb(0, 0, 0)",
        backgroundColor: "rgba(0, 0, 0, 0)",
    });

    const syncSignatureField = (value) => {
        hiddenSigInput.value = value;
        hiddenSigInput.dispatchEvent(new Event("input", { bubbles: true }));
        hiddenSigInput.dispatchEvent(new Event("change", { bubbles: true }));
    };

    const updateSourceNote = (mode) => {
        if (!signatureSourceNote) {
            return;
        }

        if (mode === "upload") {
            const fileName = signatureUploadInput.files?.[0]?.name || "uploaded signature image";
            signatureSourceNote.textContent = `Using ${fileName}. You can still clear it and draw a new signature instead.`;
            return;
        }

        if (mode === "drawn") {
            signatureSourceNote.textContent = "Using the drawn signature shown above. You can reopen the full-screen pad or upload an image if needed.";
            return;
        }

        signatureSourceNote.textContent = "Draw directly in the box, use the full-screen pad, or upload a PNG/JPG image of your signature.";
    };

    const renderPadFromValue = (pad, value) => {
        pad.clear();
        if (value) {
            pad.fromDataURL(value);
        }
    };

    const resizePad = (targetCanvas, targetPad) => {
        const existingValue = hiddenSigInput.value;
        const ratio = Math.max(window.devicePixelRatio || 1, 1);
        targetCanvas.width = targetCanvas.offsetWidth * ratio;
        targetCanvas.height = targetCanvas.offsetHeight * ratio;
        targetCanvas.getContext("2d").scale(ratio, ratio);
        targetPad.clear();

        if (existingValue && !signatureUploadInput.files?.length) {
            targetPad.fromDataURL(existingValue);
        }
    };

    const resizePads = () => {
        resizePad(canvas, signaturePad);
        resizePad(modalCanvas, modalSignaturePad);
    };

    const resizeVisiblePad = () => {
        if (canvas.offsetWidth > 0 && canvas.offsetHeight > 0) {
            resizePad(canvas, signaturePad);
        }
    };

    const useDrawnSignature = (dataUrl) => {
        if (!dataUrl) {
            return;
        }

        if (signatureUploadInput.value) {
            signatureUploadInput.value = "";
            signatureUploadInput.dispatchEvent(new Event("change", { bubbles: true }));
        }

        syncSignatureField(dataUrl);
        renderPadFromValue(signaturePad, dataUrl);
        renderPadFromValue(modalSignaturePad, dataUrl);
        updateSourceNote("drawn");
    };

    const clearAllSignatures = () => {
        signaturePad.clear();
        modalSignaturePad.clear();
        signatureUploadInput.value = "";
        syncSignatureField("");
        updateSourceNote("none");
    };

    window.addEventListener("resize", resizePads);
    resizePads();
    document.addEventListener("application-form-step-change", () => {
        window.setTimeout(resizeVisiblePad, 0);
        window.setTimeout(resizeVisiblePad, 120);
    });

    signaturePad.addEventListener("endStroke", () => {
        if (!signaturePad.isEmpty()) {
            useDrawnSignature(signaturePad.toDataURL("image/png"));
        }
    });

    modalSignaturePad.addEventListener("endStroke", () => {
        if (!modalSignaturePad.isEmpty()) {
            syncSignatureField(modalSignaturePad.toDataURL("image/png"));
            updateSourceNote("drawn");
        }
    });

    clearBtn.addEventListener("click", (event) => {
        event.preventDefault();
        clearAllSignatures();
    });

    clearModalBtn.addEventListener("click", (event) => {
        event.preventDefault();
        modalSignaturePad.clear();
        syncSignatureField("");
        renderPadFromValue(signaturePad, "");
        updateSourceNote("none");
    });

    openModalBtn.addEventListener("click", () => {
        renderPadFromValue(modalSignaturePad, hiddenSigInput.value);
        signatureModal?.show();
        window.setTimeout(() => {
            resizePad(modalCanvas, modalSignaturePad);
        }, 200);
    });

    saveModalBtn.addEventListener("click", () => {
        if (modalSignaturePad.isEmpty()) {
            alert("Please provide your signature before saving it.");
            return;
        }

        useDrawnSignature(modalSignaturePad.toDataURL("image/png"));
        signatureModal?.hide();
    });

    signatureUploadInput.addEventListener("change", () => {
        const uploadedFile = signatureUploadInput.files?.[0] ?? null;
        if (!uploadedFile) {
            updateSourceNote(hiddenSigInput.value ? "drawn" : "none");
            return;
        }

        const allowedTypes = ["image/png", "image/jpeg"];
        if (!allowedTypes.includes(uploadedFile.type)) {
            alert("Please upload a PNG or JPG image for your signature.");
            signatureUploadInput.value = "";
            return;
        }

        signaturePad.clear();
        modalSignaturePad.clear();
        syncSignatureField("");
        updateSourceNote("upload");

        const reader = new FileReader();
        reader.onload = () => {
            const imageData = typeof reader.result === "string" ? reader.result : "";
            if (!imageData) {
                return;
            }

            signaturePad.fromDataURL(imageData);
            modalSignaturePad.fromDataURL(imageData);
        };
        reader.readAsDataURL(uploadedFile);
    });

    applicationForm.addEventListener("submit", (event) => {
        const hasUpload = Boolean(signatureUploadInput.files?.length);
        if (!hasUpload && signaturePad.isEmpty() && !hiddenSigInput.value) {
            event.preventDefault();
            alert("Please provide your e-signature before submitting.");
            return;
        }

        if (!hasUpload && !signaturePad.isEmpty()) {
            syncSignatureField(signaturePad.toDataURL("image/png"));
        }
    });

    if (hiddenSigInput.value) {
        renderPadFromValue(signaturePad, hiddenSigInput.value);
        renderPadFromValue(modalSignaturePad, hiddenSigInput.value);
        updateSourceNote("drawn");
    } else {
        updateSourceNote("none");
    }
});
