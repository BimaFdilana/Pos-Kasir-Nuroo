import 'package:geolocator/geolocator.dart';

/// Hasil lokasi best-effort: koordinat boleh null.
///
/// Lokasi TIDAK PERNAH memblokir absensi. Bila izin ditolak, layanan
/// mati, atau timeout, pemanggil tetap lanjut check-in/out tanpa
/// koordinat dan menampilkan [note] ke pengguna.
class LocationResult {
  final double? latitude;
  final double? longitude;
  final String? note;

  const LocationResult({this.latitude, this.longitude, this.note});

  bool get hasCoordinates => latitude != null && longitude != null;
}

abstract class LocationProvider {
  Future<LocationResult> currentLocation();
}

class GeolocatorLocationProvider implements LocationProvider {
  @override
  Future<LocationResult> currentLocation() async {
    try {
      if (!await Geolocator.isLocationServiceEnabled()) {
        return const LocationResult(
            note: 'Layanan lokasi tidak aktif — absensi tanpa koordinat.');
      }
      var permission = await Geolocator.checkPermission();
      if (permission == LocationPermission.denied) {
        permission = await Geolocator.requestPermission();
      }
      if (permission == LocationPermission.denied ||
          permission == LocationPermission.deniedForever) {
        return const LocationResult(
            note: 'Izin lokasi ditolak — absensi tanpa koordinat.');
      }
      final position = await Geolocator.getCurrentPosition(
        locationSettings: const LocationSettings(
          accuracy: LocationAccuracy.medium,
          timeLimit: Duration(seconds: 10),
        ),
      );
      return LocationResult(
        latitude: position.latitude,
        longitude: position.longitude,
      );
    } catch (_) {
      return const LocationResult(
          note: 'Lokasi tidak tersedia — absensi tanpa koordinat.');
    }
  }
}
