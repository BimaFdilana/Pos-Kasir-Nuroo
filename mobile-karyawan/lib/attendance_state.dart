import 'models.dart';

/// Mesin status absensi di sisi klien — murni, tanpa I/O, mudah dites.
///
/// Status: belum check-in -> sudah check-in (terbuka) -> sudah check-out.
/// Guard klien: check-in dinonaktifkan saat sudah check-in, check-out
/// dinonaktifkan saat belum check-in. Guard server (422) tetap jadi
/// penentu akhir dan pesannya ditampilkan apa adanya (lihat ApiClient).
enum AttendanceStatus { notCheckedIn, checkedIn, checkedOut }

class AttendanceState {
  Attendance? openAttendance;
  Attendance? lastClosedAttendance;

  AttendanceState({this.openAttendance, this.lastClosedAttendance});

  AttendanceStatus get status {
    if (openAttendance != null && openAttendance!.isOpen) {
      return AttendanceStatus.checkedIn;
    }
    if (lastClosedAttendance != null) {
      return AttendanceStatus.checkedOut;
    }
    return AttendanceStatus.notCheckedIn;
  }

  bool get canCheckIn => status != AttendanceStatus.checkedIn;
  bool get canCheckOut => status == AttendanceStatus.checkedIn;

  /// Terapkan hasil check-in. Mengembalikan false bila guard menolak
  /// (sudah check-in) — pemanggil tidak boleh memanggil API dalam kasus ini.
  bool applyCheckIn(Attendance record) {
    if (!canCheckIn) return false;
    openAttendance = record;
    lastClosedAttendance = null;
    return true;
  }

  /// Terapkan hasil check-out. Mengembalikan false bila guard menolak
  /// (belum check-in) — pemanggil tidak boleh memanggil API.
  bool applyCheckOut(Attendance record) {
    if (!canCheckOut) return false;
    openAttendance = null;
    lastClosedAttendance = record;
    return true;
  }

  /// Sinkronkan dari GET /attendance/open: null berarti belum check-in
  /// (kecuali riwayat tertutup terakhir sudah diketahui pemanggil).
  void syncFromOpen(Attendance? open) {
    openAttendance = open;
    if (open == null) lastClosedAttendance = null;
  }
}
