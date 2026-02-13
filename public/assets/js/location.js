// location.js - Handle Geolocation

class LocationService {
  constructor(options = {}) {
    this.options = {
      enableHighAccuracy: true,
      timeout: 10000,
      maximumAge: 0,
      ...options,
    };

    this.currentPosition = null;
    this.watchId = null;
  }

  // Get current position
  getCurrentPosition() {
    return new Promise((resolve, reject) => {
      if (!navigator.geolocation) {
        reject(new Error("Geolocation is not supported by your browser"));
        return;
      }

      navigator.geolocation.getCurrentPosition(
        (position) => {
          this.currentPosition = position;
          resolve(position);
        },
        (error) => {
          reject(this.handleGeolocationError(error));
        },
        this.options,
      );
    });
  }

  // Watch position changes
  watchPosition(onSuccess, onError) {
    if (!navigator.geolocation) {
      onError(new Error("Geolocation is not supported by your browser"));
      return null;
    }

    this.watchId = navigator.geolocation.watchPosition(
      (position) => {
        this.currentPosition = position;
        onSuccess(position);
      },
      (error) => {
        onError(this.handleGeolocationError(error));
      },
      this.options,
    );

    return this.watchId;
  }

  // Stop watching position
  stopWatching() {
    if (this.watchId !== null) {
      navigator.geolocation.clearWatch(this.watchId);
      this.watchId = null;
    }
  }

  // Calculate distance between two coordinates (Haversine formula)
  calculateDistance(lat1, lon1, lat2, lon2) {
    const R = 6371000; // Earth's radius in meters
    const φ1 = (lat1 * Math.PI) / 180;
    const φ2 = (lat2 * Math.PI) / 180;
    const Δφ = ((lat2 - lat1) * Math.PI) / 180;
    const Δλ = ((lon2 - lon1) * Math.PI) / 180;

    const a =
      Math.sin(Δφ / 2) * Math.sin(Δφ / 2) +
      Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ / 2) * Math.sin(Δλ / 2);
    const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));

    return R * c; // Distance in meters
  }

  // Check if within radius
  isWithinRadius(currentLat, currentLon, targetLat, targetLon, radius) {
    const distance = this.calculateDistance(
      currentLat,
      currentLon,
      targetLat,
      targetLon,
    );
    return {
      withinRadius: distance <= radius,
      distance: distance,
    };
  }

  // Get address from coordinates (reverse geocoding)
  async getAddressFromCoordinates(latitude, longitude) {
    try {
      const response = await fetch(
        `https://nominatim.openstreetmap.org/reverse?format=json&lat=${latitude}&lon=${longitude}&zoom=18&addressdetails=1`,
      );

      if (!response.ok) {
        throw new Error("Failed to fetch address");
      }

      const data = await response.json();
      return data.display_name || "Address not found";
    } catch (error) {
      console.error("Reverse geocoding error:", error);
      return null;
    }
  }

  // Handle geolocation errors
  handleGeolocationError(error) {
    let message = "";

    switch (error.code) {
      case error.PERMISSION_DENIED:
        message = "User denied the request for Geolocation.";
        break;
      case error.POSITION_UNAVAILABLE:
        message = "Location information is unavailable.";
        break;
      case error.TIMEOUT:
        message = "The request to get user location timed out.";
        break;
      case error.UNKNOWN_ERROR:
        message = "An unknown error occurred.";
        break;
    }

    return new Error(message);
  }

  // Check if geolocation is supported
  static isSupported() {
    return "geolocation" in navigator;
  }

  // Request permission
  static async requestPermission() {
    if (!this.isSupported()) {
      return false;
    }

    try {
      // Note: Modern browsers don't allow programmatic permission requests
      // We need to call getCurrentPosition() which will trigger the permission prompt
      const position = await new Promise((resolve, reject) => {
        navigator.geolocation.getCurrentPosition(resolve, reject);
      });
      return true;
    } catch (error) {
      return false;
    }
  }

  // Get cached position
  getCachedPosition() {
    return this.currentPosition;
  }

  // Clear cached position
  clearCachedPosition() {
    this.currentPosition = null;
  }
}

// Export as global
window.LocationService = LocationService;

// Usage example:
// const locationService = new LocationService();
//
// try {
//     const position = await locationService.getCurrentPosition();
//     const distance = locationService.isWithinRadius(
//         position.coords.latitude,
//         position.coords.longitude,
//         SCHOOL_LATITUDE,
//         SCHOOL_LONGITUDE,
//         ATTENDANCE_RADIUS
//     );
// } catch (error) {
//     console.error(error);
// }
