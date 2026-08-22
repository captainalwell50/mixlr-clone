import 'package:flutter_test/flutter_test.dart';
import 'package:live_mix/models/models.dart';

void main() {
  test('GivingInfo.tryParse hides disabled or empty giving', () {
    expect(GivingInfo.tryParse(null), isNull);
    expect(GivingInfo.tryParse({'enabled': false, 'url': 'https://x.test'}), isNull);
    expect(GivingInfo.tryParse({'enabled': true}), isNull);
    expect(
      GivingInfo.tryParse({'enabled': 1, 'url': 'https://pay.example/x'})?.hasUrl,
      isTrue,
    );
  });

  test('GivingInfo.tryParse reads url, account, and note', () {
    final info = GivingInfo.tryParse({
      'enabled': true,
      'url': 'https://paystack.com/pay/grace',
      'account_name': 'Grace Chapel',
      'bank_name': 'GTBank',
      'account_number': '0123456789',
      'note': 'Sunday offering',
    });

    expect(info, isNotNull);
    expect(info!.hasUrl, isTrue);
    expect(info.hasAccount, isTrue);
    expect(info.accountNumber, '0123456789');
    expect(info.copyAll, contains('0123456789'));
  });

  test('ListenPayload reads giving from organization', () {
    final payload = ListenPayload.fromJson({
      'stream': {
        'uuid': 'fee1cf9a-e6ae-4c84-b582-150e924619ca',
        'title': 'Live',
        'status': 'live',
      },
      'organization': {
        'name': 'Grace',
        'giving': {
          'enabled': true,
          'url': 'https://paystack.com/pay/grace',
        },
      },
    });

    expect(payload.giving?.url, 'https://paystack.com/pay/grace');
  });
}
