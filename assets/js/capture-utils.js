(function (window) {
    "use strict";

    function blurScoreFromImageData(imageData) {
        if (!imageData || !imageData.data || !imageData.width || !imageData.height) {
            return 0;
        }

        var data = imageData.data;
        var width = imageData.width;
        var height = imageData.height;
        if (width < 3 || height < 3) {
            return 0;
        }

        var gray = new Float32Array(width * height);
        for (var i = 0, p = 0; i < data.length; i += 4, p += 1) {
            gray[p] = (data[i] * 0.299) + (data[i + 1] * 0.587) + (data[i + 2] * 0.114);
        }

        var sum = 0;
        var count = 0;
        for (var y = 1; y < height - 1; y += 1) {
            var row = y * width;
            for (var x = 1; x < width - 1; x += 1) {
                var idx = row + x;
                var lap = (4 * gray[idx]) - gray[idx - 1] - gray[idx + 1] - gray[idx - width] - gray[idx + width];
                sum += Math.abs(lap);
                count += 1;
            }
        }

        return count ? (sum / count) : 0;
    }

    function blurScoreFromCanvas(canvas) {
        if (!canvas || !canvas.width || !canvas.height || !canvas.getContext) {
            return 0;
        }
        var ctx = canvas.getContext("2d", { willReadFrequently: true });
        if (!ctx) {
            return 0;
        }
        return blurScoreFromImageData(ctx.getImageData(0, 0, canvas.width, canvas.height));
    }

    function stopMediaStream(stream) {
        if (!stream || !stream.getTracks) {
            return;
        }
        stream.getTracks().forEach(function (track) {
            track.stop();
        });
    }

    async function requestCamera(videoElement, constraints) {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error("Camera is not supported on this browser.");
        }
        if (!videoElement) {
            throw new Error("Video element is required.");
        }
        var stream = await navigator.mediaDevices.getUserMedia(constraints || { video: true, audio: false });
        videoElement.srcObject = stream;
        await videoElement.play();
        return stream;
    }

    window.SE_CAPTURE = {
        blurScoreFromImageData: blurScoreFromImageData,
        blurScoreFromCanvas: blurScoreFromCanvas,
        stopMediaStream: stopMediaStream,
        requestCamera: requestCamera
    };
})(window);
