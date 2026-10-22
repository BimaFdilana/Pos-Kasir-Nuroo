import 'package:flutter_test/flutter_test.dart';
import 'package:karyawan/attendance_state.dart';
import 'package:karyawan/models.dart';

Attendance _record({int id = 1, String? inAt, String? outAt}) =>
    Attendance(
      id: id,
      userId: 7,
      checkedInAt: inAt,
      checkedOutAt: outAt,
    );

void main() {
  group('AttendanceState transitions', () {
    test('awal: belum check-in, hanya check-in yang diizinkan', () {
      final state = AttendanceState();
      expect(state.status, AttendanceStatus.notCheckedIn);
      expect(state.canCheckIn, isTrue);
      expect(state.canCheckOut, isFalse);
    });

    test('belum check-in -> check-in -> check-out', () {
      final state = AttendanceState();
      expect(
        state.applyCheckIn(_record(inAt: '2026-10-23 08:00:00')),
        isTrue,
      );
      expect(state.status, AttendanceStatus.checkedIn);
      expect(state.canCheckIn, isFalse);
      expect(state.canCheckOut, isTrue);

      expect(
        state.applyCheckOut(
          _record(inAt: '2026-10-23 08:00:00', outAt: '2026-10-23 17:00:00'),
        ),
        isTrue,
      );
      expect(state.status, AttendanceStatus.checkedOut);
      expect(state.canCheckIn, isTrue);
      expect(state.canCheckOut, isFalse);
    });

    test('double check-in ditolak guard klien', () {
      final state = AttendanceState();
      state.applyCheckIn(_record(inAt: '2026-10-23 08:00:00'));
      expect(
        state.applyCheckIn(_record(id: 2, inAt: '2026-10-23 08:05:00')),
        isFalse,
      );
      // Record pertama tetap yang berlaku.
      expect(state.openAttendance!.id, 1);
    });

    test('check-out tanpa check-in ditolak guard klien', () {
      final state = AttendanceState();
      expect(
        state.applyCheckOut(
          _record(inAt: '2026-10-23 08:00:00', outAt: '2026-10-23 17:00:00'),
        ),
        isFalse,
      );
      expect(state.status, AttendanceStatus.notCheckedIn);
    });

    test('syncFromOpen(null) kembali ke belum check-in', () {
      final state = AttendanceState();
      state.applyCheckIn(_record(inAt: '2026-10-23 08:00:00'));
      state.syncFromOpen(null);
      expect(state.status, AttendanceStatus.notCheckedIn);
      expect(state.canCheckIn, isTrue);
    });

    test('syncFromOpen(record terbuka) menjadi sudah check-in', () {
      final state = AttendanceState();
      state.syncFromOpen(_record(inAt: '2026-10-23 08:00:00'));
      expect(state.status, AttendanceStatus.checkedIn);
      expect(state.canCheckOut, isTrue);
    });
  });
}
