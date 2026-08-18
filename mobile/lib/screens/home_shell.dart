import 'package:flutter/material.dart';

import '../platform_info.dart';
import '../widgets/listen_session_overlay.dart';
import '../widgets/network_banner.dart';
import 'creator_home_screen.dart';
import 'discover_screen.dart';
import 'gallery_screen.dart';
import 'profile_screen.dart';
import 'scripture_screen.dart';

class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  int _index = 0;

  static const _destinations = [
    NavigationDestination(
      icon: Icon(Icons.headphones_outlined),
      selectedIcon: Icon(Icons.headphones),
      label: 'Listen',
    ),
    NavigationDestination(
      icon: Icon(Icons.menu_book_outlined),
      selectedIcon: Icon(Icons.menu_book_rounded),
      label: 'Scripture',
    ),
    NavigationDestination(
      icon: Icon(Icons.photo_library_outlined),
      selectedIcon: Icon(Icons.photo_library),
      label: 'Gallery',
    ),
    NavigationDestination(
      icon: Icon(Icons.mic_none),
      selectedIcon: Icon(Icons.mic),
      label: 'Studio',
    ),
    NavigationDestination(
      icon: Icon(Icons.person_outline_rounded),
      selectedIcon: Icon(Icons.person_rounded),
      label: 'Profile',
    ),
  ];

  static const _railDestinations = [
    NavigationRailDestination(
      icon: Icon(Icons.headphones_outlined),
      selectedIcon: Icon(Icons.headphones),
      label: Text('Listen'),
    ),
    NavigationRailDestination(
      icon: Icon(Icons.menu_book_outlined),
      selectedIcon: Icon(Icons.menu_book_rounded),
      label: Text('Scripture'),
    ),
    NavigationRailDestination(
      icon: Icon(Icons.photo_library_outlined),
      selectedIcon: Icon(Icons.photo_library),
      label: Text('Gallery'),
    ),
    NavigationRailDestination(
      icon: Icon(Icons.mic_none),
      selectedIcon: Icon(Icons.mic),
      label: Text('Studio'),
    ),
    NavigationRailDestination(
      icon: Icon(Icons.person_outline_rounded),
      selectedIcon: Icon(Icons.person_rounded),
      label: Text('Profile'),
    ),
  ];

  @override
  Widget build(BuildContext context) {
    final desktop = PlatformInfo.isDesktop;

    final body = ListenSessionOverlay(
      child: Column(
        children: [
          const NetworkBanner(),
          Expanded(
            child: IndexedStack(
              index: _index,
              children: const [
                DiscoverScreen(),
                ScriptureScreen(),
                GalleryScreen(),
                CreatorHomeScreen(),
                ProfileScreen(),
              ],
            ),
          ),
        ],
      ),
    );

    if (desktop) {
      return Scaffold(
        body: Row(
          children: [
            NavigationRail(
              selectedIndex: _index,
              onDestinationSelected: (i) => setState(() => _index = i),
              labelType: NavigationRailLabelType.all,
              backgroundColor: const Color(0xFF12151C),
              destinations: _railDestinations,
            ),
            const VerticalDivider(width: 1, thickness: 1),
            Expanded(
              child: SafeArea(
                child: body,
              ),
            ),
          ],
        ),
      );
    }

    return Scaffold(
      body: SafeArea(
        bottom: false,
        child: body,
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        destinations: _destinations,
      ),
    );
  }
}
