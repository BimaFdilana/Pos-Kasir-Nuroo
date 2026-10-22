import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:karyawan/api_client.dart';
import 'package:karyawan/models.dart';

void main() {
  group('Parsing respons API', () {
    test('attendance terbuka: data record -> isOpen true', () async {
      final client = MockClient((request) async {
        expect(request.url.path, '/api/v1/attendance/open');
        return http.Response(
          jsonEncode({
            'data': {
              'id': 5,
              'user_id': 7,
              'outlet_id': 2,
              'checked_in_at': '2026-10-23 08:00:00',
              'checked_out_at': null,
              'latitude': -0.5,
              'longitude': 101.4,
              'note': null,
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      });
      final api = ApiClient(baseUrl: 'http://x', httpClient: client);
      final open = await api.getOpenAttendance();
      expect(open, isNotNull);
      expect(open!.isOpen, isTrue);
      expect(open.latitude, -0.5);
    });

    test('attendance terbuka: data null -> null (belum check-in)', () async {
      final client = MockClient((_) async => http.Response(
            jsonEncode({'data': null}),
            200,
            headers: {'content-type': 'application/json'},
          ));
      final api = ApiClient(baseUrl: 'http://x', httpClient: client);
      expect(await api.getOpenAttendance(), isNull);
    });

    test('check-in ganda: 422 diteruskan sebagai ApiException', () async {
      final client = MockClient((request) async {
        if (request.url.path.endsWith('/check-in')) {
          return http.Response(
            jsonEncode({'message': 'Anda sudah memiliki check-in terbuka.'}),
            422,
            headers: {'content-type': 'application/json'},
          );
        }
        return http.Response('{}', 200);
      });
      final api = ApiClient(baseUrl: 'http://x', httpClient: client);
      await expectLater(
        api.checkIn(),
        throwsA(
          isA<ApiException>().having(
            (e) => e.message,
            'message',
            contains('sudah memiliki check-in terbuka'),
          ),
        ),
      );
    });

    test('check-out tanpa check-in: 422 diteruskan sebagai ApiException',
        () async {
      final client = MockClient((_) async => http.Response(
            jsonEncode({'message': 'Tidak ada check-in terbuka.'}),
            422,
            headers: {'content-type': 'application/json'},
          ));
      final api = ApiClient(baseUrl: 'http://x', httpClient: client);
      await expectLater(
        api.checkOut(),
        throwsA(isA<ApiException>()),
      );
    });

    test('riwayat me: paginator Laravel diparsing', () async {
      final client = MockClient((_) async => http.Response(
            jsonEncode({
              'data': {
                'data': [
                  {
                    'id': 1,
                    'user_id': 7,
                    'checked_in_at': '2026-10-22 08:00:00',
                    'checked_out_at': '2026-10-22 17:00:00',
                  },
                  {
                    'id': 2,
                    'user_id': 7,
                    'checked_in_at': '2026-10-23 08:00:00',
                    'checked_out_at': null,
                  },
                ],
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          ));
      final api = ApiClient(baseUrl: 'http://x', httpClient: client);
      final history = await api.getMyAttendance();
      expect(history.length, 2);
      expect(history[0].isOpen, isFalse);
      expect(history[1].isOpen, isTrue);
    });

    test('shift my: paginator + outlet diparsing', () async {
      final client = MockClient((_) async => http.Response(
            jsonEncode({
              'data': {
                'data': [
                  {
                    'id': 3,
                    'date': '2026-10-24',
                    'start_time': '08:00',
                    'end_time': '16:00',
                    'note': 'Shift pagi',
                    'outlet': {'id': 2, 'name': 'Outlet Utama'},
                  },
                ],
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          ));
      final api = ApiClient(baseUrl: 'http://x', httpClient: client);
      final shifts = await api.getMyShifts();
      expect(shifts.length, 1);
      expect(shifts[0].outletName, 'Outlet Utama');
      expect(shifts[0].startTime, '08:00');
    });
  });

  group('Model parsing', () {
    test('Attendance.fromJson: checkout null berarti terbuka', () {
      final a = Attendance.fromJson({
        'id': 1,
        'user_id': 7,
        'checked_in_at': '2026-10-23 08:00:00',
        'checked_out_at': null,
      });
      expect(a.isOpen, isTrue);
    });

    test('paginatorItems: list langsung juga didukung', () {
      final items = paginatorItems({
        'data': [
          {'id': 1},
        ],
      });
      expect(items.length, 1);
    });
  });
}
