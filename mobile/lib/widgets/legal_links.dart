import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../config.dart';
import '../theme.dart';

Future<void> openExternalUrl(String url) async {
  final uri = Uri.parse(url);
  if (!await launchUrl(uri, mode: LaunchMode.externalApplication)) {
    throw Exception('Could not open $url');
  }
}

/// Compact Privacy / Terms / Support row for login, welcome, and Studio.
class LegalLinks extends StatelessWidget {
  const LegalLinks({super.key, this.dense = false});

  final bool dense;

  @override
  Widget build(BuildContext context) {
    final style = TextStyle(
      color: LiveMixTheme.mute,
      fontSize: dense ? 12 : 13,
      height: 1.3,
    );
    return Wrap(
      alignment: WrapAlignment.center,
      crossAxisAlignment: WrapCrossAlignment.center,
      spacing: 6,
      runSpacing: 4,
      children: [
        TextButton(
          onPressed: () => openExternalUrl(AppConfig.privacyUrl),
          style: TextButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text('Privacy', style: style.copyWith(decoration: TextDecoration.underline)),
        ),
        Text('·', style: style),
        TextButton(
          onPressed: () => openExternalUrl(AppConfig.termsUrl),
          style: TextButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text('Terms', style: style.copyWith(decoration: TextDecoration.underline)),
        ),
        Text('·', style: style),
        TextButton(
          onPressed: () => openExternalUrl(AppConfig.supportUrl),
          style: TextButton.styleFrom(
            padding: const EdgeInsets.symmetric(horizontal: 4),
            minimumSize: Size.zero,
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: Text('Support', style: style.copyWith(decoration: TextDecoration.underline)),
        ),
      ],
    );
  }
}
