import 'package:flutter_test/flutter_test.dart';
import 'package:soundmix_studio/scripture/spoken_reference.dart';

void main() {
  test('spoken reference smoke — John 3:16', () {
    expect(parseSpokenReference('John 3:16'), 'John 3:16');
  });
}
