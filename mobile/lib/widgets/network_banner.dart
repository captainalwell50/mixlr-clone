import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../services/network_status.dart';
import '../theme.dart';

class NetworkBanner extends StatelessWidget {
  const NetworkBanner({super.key});

  @override
  Widget build(BuildContext context) {
    final net = context.watch<NetworkStatus>();
    if (net.health == NetHealth.online) return const SizedBox.shrink();

    final color = net.health == NetHealth.offline
        ? LiveMixTheme.bad
        : LiveMixTheme.warn;
    final bg = net.health == NetHealth.offline
        ? const Color(0xFF3A1A1A)
        : const Color(0xFF3A2E14);
    final message = net.health == NetHealth.offline
        ? 'You’re offline — showing saved content where available'
        : 'Network issue — ${net.label}. Retrying…';

    return Material(
      color: bg,
      child: InkWell(
        onTap: () => net.refresh(),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 10),
          child: Row(
            children: [
              Icon(
                net.health == NetHealth.offline
                    ? Icons.cloud_off_rounded
                    : Icons.wifi_tethering_error_rounded,
                size: 18,
                color: color,
              ),
              const SizedBox(width: 10),
              Expanded(
                child: Text(
                  message,
                  style: TextStyle(
                    color: color,
                    fontSize: 13,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
              Text(
                'Retry',
                style: TextStyle(
                  color: color,
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class NetworkPill extends StatelessWidget {
  const NetworkPill({super.key});

  @override
  Widget build(BuildContext context) {
    final net = context.watch<NetworkStatus>();
    final color = switch (net.health) {
      NetHealth.online => LiveMixTheme.good,
      NetHealth.degraded => LiveMixTheme.warn,
      NetHealth.offline => LiveMixTheme.bad,
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: LiveMixTheme.panelHi,
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: color.withOpacity(0.45)),
      ),
      child: Row(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 7,
            height: 7,
            decoration: BoxDecoration(color: color, shape: BoxShape.circle),
          ),
          const SizedBox(width: 6),
          Text(
            net.label,
            style: TextStyle(
              color: color,
              fontSize: 11,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
      ),
    );
  }
}
