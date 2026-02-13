// camera.js - Handle Camera and Face Recognition

class CameraService {
  constructor(videoElement, options = {}) {
    this.video = videoElement;
    this.options = {
      facingMode: "user",
      width: 640,
      height: 480,
      ...options,
    };

    this.stream = null;
    this.canvas = document.createElement("canvas");
    this.context = this.canvas.getContext("2d");
    this.faceDetectionModel = null;
    this.isFaceDetected = false;
  }

  // Initialize camera
  async initialize() {
    try {
      const constraints = {
        video: {
          facingMode: this.options.facingMode,
          width: { ideal: this.options.width },
          height: { ideal: this.options.height },
        },
        audio: false,
      };

      this.stream = await navigator.mediaDevices.getUserMedia(constraints);
      this.video.srcObject = this.stream;

      // Set canvas dimensions to match video
      this.video.addEventListener("loadedmetadata", () => {
        this.canvas.width = this.video.videoWidth;
        this.canvas.height = this.video.videoHeight;
      });

      return true;
    } catch (error) {
      console.error("Error accessing camera:", error);
      throw error;
    }
  }

  // Capture photo
  capturePhoto(quality = 0.8) {
    if (!this.stream) {
      throw new Error("Camera not initialized");
    }

    // Draw current video frame to canvas
    this.context.save();
    // Flip horizontally for mirror effect
    this.context.scale(-1, 1);
    this.context.drawImage(
      this.video,
      -this.canvas.width,
      0,
      this.canvas.width,
      this.canvas.height,
    );
    this.context.restore();

    // Convert to blob
    return new Promise((resolve) => {
      this.canvas.toBlob(
        (blob) => {
          resolve(blob);
        },
        "image/jpeg",
        quality,
      );
    });
  }

  // Capture photo as base64
  async capturePhotoAsBase64(quality = 0.8) {
    const blob = await this.capturePhoto(quality);
    return this.blobToBase64(blob);
  }

  // Convert blob to base64
  blobToBase64(blob) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.readAsDataURL(blob);
      reader.onloadend = () => {
        resolve(reader.result);
      };
      reader.onerror = reject;
    });
  }

  // Stop camera
  stop() {
    if (this.stream) {
      this.stream.getTracks().forEach((track) => track.stop());
      this.stream = null;
    }

    if (this.video.srcObject) {
      this.video.srcObject = null;
    }
  }

  // Switch camera (front/back)
  async switchCamera() {
    this.options.facingMode =
      this.options.facingMode === "user" ? "environment" : "user";
    this.stop();
    await this.initialize();
  }

  // Take multiple photos
  async takeMultiplePhotos(count = 3, interval = 500) {
    const photos = [];

    for (let i = 0; i < count; i++) {
      const photo = await this.capturePhotoAsBase64();
      photos.push(photo);

      if (i < count - 1) {
        await this.sleep(interval);
      }
    }

    return photos;
  }

  // Sleep helper
  sleep(ms) {
    return new Promise((resolve) => setTimeout(resolve, ms));
  }

  // Initialize face detection (using face-api.js or similar)
  async initializeFaceDetection() {
    // This is a placeholder for face detection initialization
    // In a real implementation, you would load face-api.js models here

    console.log("Face detection initialized (placeholder)");
    return true;
  }

  // Detect face in current frame
  async detectFace() {
    if (!this.faceDetectionModel) {
      await this.initializeFaceDetection();
    }

    // Draw current frame to canvas
    this.context.save();
    this.context.scale(-1, 1);
    this.context.drawImage(
      this.video,
      -this.canvas.width,
      0,
      this.canvas.width,
      this.canvas.height,
    );
    this.context.restore();

    // Placeholder for face detection logic
    // In real implementation, use face-api.js or similar

    // Simulate face detection
    const hasFace = Math.random() > 0.2; // 80% chance of detecting a face
    this.isFaceDetected = hasFace;

    return {
      success: true,
      hasFace: hasFace,
      confidence: hasFace ? Math.random() * 0.5 + 0.5 : 0, // 0.5 to 1.0 if face detected
      landmarks: [],
    };
  }

  // Check if face is properly positioned
  async checkFacePosition() {
    const detection = await this.detectFace();

    if (!detection.hasFace) {
      return {
        success: false,
        message: "No face detected",
        position: "none",
      };
    }

    // Simulate face position check
    const positions = ["center", "left", "right", "up", "down"];
    const position = positions[Math.floor(Math.random() * positions.length)];

    return {
      success: position === "center",
      message:
        position === "center" ? "Face is centered" : `Face is ${position}`,
      position: position,
    };
  }

  // Get camera capabilities
  async getCameraCapabilities() {
    if (!this.stream) {
      await this.initialize();
    }

    const videoTrack = this.stream.getVideoTracks()[0];
    const capabilities = videoTrack.getCapabilities();
    const settings = videoTrack.getSettings();

    return {
      capabilities,
      settings,
      label: videoTrack.label,
    };
  }

  // Adjust camera settings
  async adjustCameraSetting(setting, value) {
    if (!this.stream) {
      throw new Error("Camera not initialized");
    }

    const videoTrack = this.stream.getVideoTracks()[0];
    const capabilities = videoTrack.getCapabilities();

    if (capabilities[setting]) {
      const constraint = {};
      constraint[setting] = value;

      await videoTrack.applyConstraints({ advanced: [constraint] });
      return true;
    }

    return false;
  }
}

// Face Recognition Service
class FaceRecognitionService {
  constructor() {
    this.modelLoaded = false;
    this.referenceEmbedding = null;
  }

  // Load face recognition model
  async loadModel() {
    // Placeholder for loading face recognition model
    // In real implementation, this would load TensorFlow or face-api.js models

    console.log("Face recognition model loaded (placeholder)");
    this.modelLoaded = true;
    return true;
  }

  // Compute face embedding
  async computeEmbedding(imageElement) {
    if (!this.modelLoaded) {
      await this.loadModel();
    }

    // Placeholder for face embedding computation
    // In real implementation, this would use a neural network to compute embeddings

    // Simulate embedding computation
    const embedding = new Array(128).fill(0).map(() => Math.random());
    return embedding;
  }

  // Compare two face embeddings
  compareEmbeddings(embedding1, embedding2) {
    if (embedding1.length !== embedding2.length) {
      throw new Error("Embeddings must have the same length");
    }

    // Calculate cosine similarity
    let dotProduct = 0;
    let norm1 = 0;
    let norm2 = 0;

    for (let i = 0; i < embedding1.length; i++) {
      dotProduct += embedding1[i] * embedding2[i];
      norm1 += embedding1[i] * embedding1[i];
      norm2 += embedding2[i] * embedding2[i];
    }

    norm1 = Math.sqrt(norm1);
    norm2 = Math.sqrt(norm2);

    const similarity = dotProduct / (norm1 * norm2);
    const score = similarity * 100;

    return {
      score: score,
      match: score >= 70, // 70% threshold
      similarity: similarity,
    };
  }

  // Verify face against reference
  async verifyFace(referenceImage, capturedImage) {
    const refEmbedding = await this.computeEmbedding(referenceImage);
    const capEmbedding = await this.computeEmbedding(capturedImage);

    return this.compareEmbeddings(refEmbedding, capEmbedding);
  }

  // Set reference embedding
  setReferenceEmbedding(embedding) {
    this.referenceEmbedding = embedding;
  }

  // Verify against stored reference
  async verifyAgainstReference(capturedImage) {
    if (!this.referenceEmbedding) {
      throw new Error("Reference embedding not set");
    }

    const capEmbedding = await this.computeEmbedding(capturedImage);
    return this.compareEmbeddings(this.referenceEmbedding, capEmbedding);
  }
}

// Export as global
window.CameraService = CameraService;
window.FaceRecognitionService = FaceRecognitionService;

// Utility function to check camera support
function checkCameraSupport() {
  return (
    "mediaDevices" in navigator && "getUserMedia" in navigator.mediaDevices
  );
}

// Request camera permission
async function requestCameraPermission() {
  try {
    const stream = await navigator.mediaDevices.getUserMedia({ video: true });
    stream.getTracks().forEach((track) => track.stop());
    return true;
  } catch (error) {
    return false;
  }
}

// Get available cameras
async function getAvailableCameras() {
  try {
    const devices = await navigator.mediaDevices.enumerateDevices();
    return devices.filter((device) => device.kind === "videoinput");
  } catch (error) {
    console.error("Error enumerating devices:", error);
    return [];
  }
}
