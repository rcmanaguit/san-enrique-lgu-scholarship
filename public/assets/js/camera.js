document.addEventListener("DOMContentLoaded", () => {
    const video = document.getElementById("camera-stream");
    const canvas = document.getElementById("camera-canvas");
    const startBtn = document.getElementById("start-camera-btn");
    const snapBtn = document.getElementById("snap-btn");
    const retakeBtn = document.getElementById("retake-btn");
    const placeholder = document.getElementById("camera-placeholder");
    const hiddenPhotoInput = document.getElementById("id_picture_base64");
    const photoUploadInput = document.getElementById("id_picture_upload");
    const photoSourceNote = document.getElementById("photo-source-note");

    const openModalBtn = document.getElementById("open-camera-modal-btn");
    const cameraModalElement = document.getElementById("camera-modal");
    const modalVideo = document.getElementById("camera-modal-stream");
    const modalCanvas = document.getElementById("camera-modal-canvas");
    const modalPlaceholder = document.getElementById("camera-modal-placeholder");
    const startModalBtn = document.getElementById("start-camera-modal-btn");
    const snapModalBtn = document.getElementById("snap-modal-btn");
    const retakeModalBtn = document.getElementById("retake-modal-btn");

    if (
        !video ||
        !canvas ||
        !startBtn ||
        !snapBtn ||
        !retakeBtn ||
        !placeholder ||
        !hiddenPhotoInput ||
        !photoUploadInput ||
        !photoSourceNote ||
        !openModalBtn ||
        !cameraModalElement ||
        !modalVideo ||
        !modalCanvas ||
        !modalPlaceholder ||
        !startModalBtn ||
        !snapModalBtn ||
        !retakeModalBtn
    ) {
        return;
    }

    const cameraModal = window.bootstrap?.Modal ? new window.bootstrap.Modal(cameraModalElement) : null;
    let stream = null;

    const syncPhotoField = (imageData) => {
        hiddenPhotoInput.value = imageData;
        hiddenPhotoInput.dispatchEvent(new Event("input", { bubbles: true }));
        hiddenPhotoInput.dispatchEvent(new Event("change", { bubbles: true }));
    };

    const updatePhotoSourceNote = (mode) => {
        if (mode === "upload") {
            const fileName = photoUploadInput.files?.[0]?.name || "uploaded 2x2 photo";
            photoSourceNote.textContent = `Using ${fileName}. Please make sure it is a clear recent photo with a plain background.`;
            return;
        }

        if (mode === "capture") {
            photoSourceNote.textContent = "Using the captured 2x2 photo shown above. You can retake it or upload another photo if needed.";
            return;
        }

        photoSourceNote.textContent = "Use a clear recent photo with your face centered, shoulders visible, and a plain background.";
    };

    const stopStream = () => {
        if (stream) {
            stream.getTracks().forEach((track) => track.stop());
            stream = null;
        }
    };

    const renderPreview = (imageData) => {
        if (!imageData) {
            canvas.style.display = "none";
            video.style.display = "none";
            placeholder.style.display = "block";
            startBtn.style.display = "inline-block";
            snapBtn.style.display = "none";
            retakeBtn.style.display = "none";

            modalCanvas.style.display = "none";
            modalVideo.style.display = "none";
            modalPlaceholder.style.display = "block";
            startModalBtn.style.display = "inline-block";
            snapModalBtn.style.display = "none";
            retakeModalBtn.style.display = "none";
            return;
        }

        const previewImage = new Image();
        previewImage.onload = () => {
            [
                { targetCanvas: canvas, showModal: false },
                { targetCanvas: modalCanvas, showModal: true },
            ].forEach(({ targetCanvas, showModal }) => {
                const context = targetCanvas.getContext("2d");
                targetCanvas.width = previewImage.width;
                targetCanvas.height = previewImage.height;
                context.clearRect(0, 0, targetCanvas.width, targetCanvas.height);
                context.drawImage(previewImage, 0, 0, targetCanvas.width, targetCanvas.height);

                if (showModal) {
                    modalVideo.style.display = "none";
                    modalPlaceholder.style.display = "none";
                    modalCanvas.style.display = "block";
                    startModalBtn.style.display = "none";
                    snapModalBtn.style.display = "none";
                    retakeModalBtn.style.display = "inline-block";
                } else {
                    video.style.display = "none";
                    placeholder.style.display = "none";
                    canvas.style.display = "block";
                    startBtn.style.display = "none";
                    snapBtn.style.display = "none";
                    retakeBtn.style.display = "inline-block";
                }
            });
        };
        previewImage.src = imageData;
    };

    const startCamera = async (target = "inline") => {
        try {
            stopStream();
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "user" }, audio: false });
            if (target === "modal") {
                modalVideo.srcObject = stream;
                modalVideo.style.display = "block";
                modalPlaceholder.style.display = "none";
                modalCanvas.style.display = "none";
                startModalBtn.style.display = "none";
                snapModalBtn.style.display = "inline-block";
                retakeModalBtn.style.display = "none";
            } else {
                video.srcObject = stream;
                video.style.display = "block";
                placeholder.style.display = "none";
                canvas.style.display = "none";
                startBtn.style.display = "none";
                snapBtn.style.display = "inline-block";
                retakeBtn.style.display = "none";
            }
        } catch (error) {
            alert("Camera access denied or unavailable. Please use the upload photo option instead.");
            console.error(error);
        }
    };

    const capturePhoto = (source = "inline") => {
        const activeVideo = source === "modal" ? modalVideo : video;
        const targetCanvas = source === "modal" ? modalCanvas : canvas;
        const context = targetCanvas.getContext("2d");

        targetCanvas.width = 600;
        targetCanvas.height = 600;
        context.drawImage(activeVideo, 0, 0, targetCanvas.width, targetCanvas.height);

        const imageData = targetCanvas.toDataURL("image/jpeg");
        if (photoUploadInput.value) {
            photoUploadInput.value = "";
            photoUploadInput.dispatchEvent(new Event("change", { bubbles: true }));
        }

        syncPhotoField(imageData);
        renderPreview(imageData);
        updatePhotoSourceNote("capture");
        stopStream();
    };

    const clearPhoto = (restartTarget = "inline") => {
        stopStream();
        syncPhotoField("");
        if (photoUploadInput.value) {
            photoUploadInput.value = "";
            photoUploadInput.dispatchEvent(new Event("change", { bubbles: true }));
        }
        renderPreview("");
        updatePhotoSourceNote("none");

        if (restartTarget === "modal") {
            startCamera("modal");
        } else {
            startCamera("inline");
        }
    };

    startBtn.addEventListener("click", () => {
        startCamera("inline");
    });

    snapBtn.addEventListener("click", () => {
        capturePhoto("inline");
    });

    retakeBtn.addEventListener("click", () => {
        clearPhoto("inline");
    });

    openModalBtn.addEventListener("click", () => {
        cameraModal?.show();
        if (hiddenPhotoInput.value) {
            renderPreview(hiddenPhotoInput.value);
        } else {
            renderPreview("");
        }
    });

    startModalBtn.addEventListener("click", () => {
        startCamera("modal");
    });

    snapModalBtn.addEventListener("click", () => {
        capturePhoto("modal");
        cameraModal?.hide();
    });

    retakeModalBtn.addEventListener("click", () => {
        clearPhoto("modal");
    });

    cameraModalElement.addEventListener("hidden.bs.modal", () => {
        stopStream();
        if (hiddenPhotoInput.value) {
            renderPreview(hiddenPhotoInput.value);
        } else {
            renderPreview("");
        }
    });

    photoUploadInput.addEventListener("change", () => {
        const uploadedFile = photoUploadInput.files?.[0] ?? null;
        if (!uploadedFile) {
            updatePhotoSourceNote(hiddenPhotoInput.value ? "capture" : "none");
            return;
        }

        const allowedTypes = ["image/png", "image/jpeg"];
        if (!allowedTypes.includes(uploadedFile.type)) {
            alert("Please upload a PNG or JPG image for the 2x2 photo.");
            photoUploadInput.value = "";
            return;
        }

        stopStream();
        syncPhotoField("");
        updatePhotoSourceNote("upload");

        const reader = new FileReader();
        reader.onload = () => {
            const imageData = typeof reader.result === "string" ? reader.result : "";
            if (!imageData) {
                return;
            }

            renderPreview(imageData);
        };
        reader.readAsDataURL(uploadedFile);
    });

    if (hiddenPhotoInput.value) {
        renderPreview(hiddenPhotoInput.value);
        updatePhotoSourceNote("capture");
    } else {
        renderPreview("");
        updatePhotoSourceNote("none");
    }
});
