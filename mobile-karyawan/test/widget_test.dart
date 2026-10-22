import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:karyawan/main.dart';
import 'package:karyawan/session.dart';

void main() {
  testWidgets('Halaman login menampilkan base URL default & tombol Masuk',
      (WidgetTester tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: LoginScreen(
          session: Session(),
          onLoggedIn: () {},
        ),
      ),
    );

    // Default emulator, field email/kata sandi, tombol Masuk.
    expect(find.text('http://10.0.2.2:8000'), findsOneWidget);
    expect(find.text('Email'), findsOneWidget);
    expect(find.text('Kata sandi'), findsOneWidget);
    expect(find.text('Masuk'), findsOneWidget);
    expect(find.text('Pos-Kasir-Nuroo Karyawan'), findsOneWidget);
  });
}
