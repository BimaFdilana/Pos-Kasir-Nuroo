// Model respons API absensi & shift (lihat README untuk daftar endpoint).
// Catatan: aplikasi ini TIDAK menangani pembayaran sama sekali — tidak
// ada nomor kartu, tidak ada gateway, tidak ada data pembayaran.

class AppUser {
  final int id;
  final String name;
  final String email;
  final int? tenantId;
  final int? outletId;
  final String? role;

  AppUser({
    required this.id,
    required this.name,
    required this.email,
    this.tenantId,
    this.outletId,
    this.role,
  });

  factory AppUser.fromJson(Map<String, dynamic> json) => AppUser(
        id: (json['id'] as num?)?.toInt() ?? 0,
        name: (json['name'] as String?) ?? '',
        email: (json['email'] as String?) ?? '',
        tenantId: (json['tenant_id'] as num?)?.toInt(),
        outletId: (json['outlet_id'] as num?)?.toInt(),
        role: json['role'] as String?,
      );
}

class Attendance {
  final int id;
  final int userId;
  final int? outletId;
  final String? checkedInAt;
  final String? checkedOutAt;
  final double? latitude;
  final double? longitude;
  final String? note;
  final double? checkoutLatitude;
  final double? checkoutLongitude;
  final String? checkoutNote;

  Attendance({
    required this.id,
    required this.userId,
    this.outletId,
    this.checkedInAt,
    this.checkedOutAt,
    this.latitude,
    this.longitude,
    this.note,
    this.checkoutLatitude,
    this.checkoutLongitude,
    this.checkoutNote,
  });

  /// Check-in masih terbuka bila sudah check-in tapi belum check-out.
  bool get isOpen => checkedInAt != null && checkedOutAt == null;

  factory Attendance.fromJson(Map<String, dynamic> json) => Attendance(
        id: (json['id'] as num?)?.toInt() ?? 0,
        userId: (json['user_id'] as num?)?.toInt() ?? 0,
        outletId: (json['outlet_id'] as num?)?.toInt(),
        checkedInAt: json['checked_in_at'] as String?,
        checkedOutAt: json['checked_out_at'] as String?,
        latitude: (json['latitude'] as num?)?.toDouble(),
        longitude: (json['longitude'] as num?)?.toDouble(),
        note: json['note'] as String?,
        checkoutLatitude: (json['checkout_latitude'] as num?)?.toDouble(),
        checkoutLongitude:
            (json['checkout_longitude'] as num?)?.toDouble(),
        checkoutNote: json['checkout_note'] as String?,
      );
}

class Shift {
  final int id;
  final String date;
  final String startTime;
  final String endTime;
  final String? note;
  final int? outletId;
  final String? outletName;

  Shift({
    required this.id,
    required this.date,
    required this.startTime,
    required this.endTime,
    this.note,
    this.outletId,
    this.outletName,
  });

  factory Shift.fromJson(Map<String, dynamic> json) {
    final outlet = json['outlet'] as Map<String, dynamic>?;
    return Shift(
      id: (json['id'] as num?)?.toInt() ?? 0,
      date: (json['date'] as String?) ?? '',
      startTime: (json['start_time'] as String?) ?? '',
      endTime: (json['end_time'] as String?) ?? '',
      note: json['note'] as String?,
      outletId: (outlet?['id'] as num?)?.toInt(),
      outletName: outlet?['name'] as String?,
    );
  }
}

/// Ambil list dari respons paginator Laravel `{data: {data: [...]}}`
/// atau dari respons list langsung `{data: [...]}`.
List<Map<String, dynamic>> paginatorItems(Map<String, dynamic> body) {
  final data = body['data'];
  if (data is List) {
    return data.map((e) => e as Map<String, dynamic>).toList();
  }
  if (data is Map<String, dynamic>) {
    final inner = data['data'];
    if (inner is List) {
      return inner.map((e) => e as Map<String, dynamic>).toList();
    }
  }
  return [];
}
