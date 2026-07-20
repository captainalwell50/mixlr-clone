class AppUser {
  AppUser({
    required this.id,
    required this.name,
    required this.email,
    required this.onboarded,
    required this.organizations,
  });

  final int id;
  final String name;
  final String email;
  final bool onboarded;
  final List<OrgSummary> organizations;

  factory AppUser.fromJson(Map<String, dynamic> json) {
    final orgs = (json['organizations'] as List<dynamic>? ?? [])
        .map((e) => OrgSummary.fromJson(e as Map<String, dynamic>))
        .toList();
    return AppUser(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      email: json['email'] as String? ?? '',
      onboarded: json['onboarded'] as bool? ?? false,
      organizations: orgs,
    );
  }
}

class OrgSummary {
  OrgSummary({
    required this.id,
    required this.name,
    required this.slug,
    this.themeColor,
    this.artworkUrl,
    this.canBroadcast = false,
  });

  final int id;
  final String name;
  final String slug;
  final String? themeColor;
  final String? artworkUrl;
  final bool canBroadcast;

  factory OrgSummary.fromJson(Map<String, dynamic> json) {
    return OrgSummary(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      themeColor: json['theme_color'] as String?,
      artworkUrl: json['artwork_url'] as String?,
      canBroadcast: json['can_broadcast'] as bool? ?? false,
    );
  }
}

class StreamSummary {
  StreamSummary({
    required this.uuid,
    required this.title,
    required this.status,
    this.description,
    this.isPublic = true,
    this.chatEnabled = true,
    this.listenUrl,
  });

  final String uuid;
  final String title;
  final String status;
  final String? description;
  final bool isPublic;
  final bool chatEnabled;
  final String? listenUrl;

  bool get isLive => status == 'live';

  factory StreamSummary.fromJson(Map<String, dynamic> json) {
    return StreamSummary(
      uuid: json['uuid'] as String,
      title: json['title'] as String? ?? 'Untitled',
      status: json['status'] as String? ?? 'offline',
      description: json['description'] as String?,
      isPublic: json['is_public'] as bool? ?? true,
      chatEnabled: json['chat_enabled'] as bool? ?? true,
      listenUrl: json['listen_url'] as String?,
    );
  }
}

class DiscoverCard {
  DiscoverCard({
    required this.uuid,
    required this.title,
    required this.status,
    this.organization,
    this.themeColor,
    this.artworkUrl,
    this.hlsUrl,
  });

  final String uuid;
  final String title;
  final String status;
  final String? organization;
  final String? themeColor;
  final String? artworkUrl;
  final String? hlsUrl;

  factory DiscoverCard.fromJson(Map<String, dynamic> json) {
    return DiscoverCard(
      uuid: json['uuid'] as String,
      title: json['title'] as String? ?? 'Live',
      status: json['status'] as String? ?? 'live',
      organization: json['organization'] as String?,
      themeColor: json['theme_color'] as String?,
      artworkUrl: json['artwork_url'] as String?,
      hlsUrl: json['hls_url'] as String?,
    );
  }
}

class ListenPayload {
  ListenPayload({
    required this.uuid,
    required this.title,
    required this.status,
    this.description,
    this.chatEnabled = true,
    this.hlsUrl,
    this.whepUrl,
    this.orgName,
    this.themeColor,
    this.artworkUrl,
  });

  final String uuid;
  final String title;
  final String status;
  final String? description;
  final bool chatEnabled;
  final String? hlsUrl;
  final String? whepUrl;
  final String? orgName;
  final String? themeColor;
  final String? artworkUrl;

  bool get isLive => status == 'live';

  factory ListenPayload.fromJson(Map<String, dynamic> json) {
    final stream = json['stream'] as Map<String, dynamic>? ?? {};
    final org = json['organization'] as Map<String, dynamic>?;
    return ListenPayload(
      uuid: stream['uuid'] as String,
      title: stream['title'] as String? ?? 'Live',
      status: stream['status'] as String? ?? 'offline',
      description: stream['description'] as String?,
      chatEnabled: stream['chat_enabled'] as bool? ?? true,
      hlsUrl: stream['hls_url'] as String?,
      whepUrl: stream['whep_url'] as String?,
      orgName: org?['name'] as String?,
      themeColor: org?['theme_color'] as String?,
      artworkUrl: org?['artwork_url'] as String?,
    );
  }
}

class CreatorHome {
  CreatorHome({
    required this.onboarded,
    required this.canBroadcast,
    this.organization,
    this.stream,
    this.streams = const [],
  });

  final bool onboarded;
  final bool canBroadcast;
  final OrgSummary? organization;
  final StreamSummary? stream;
  final List<StreamSummary> streams;

  factory CreatorHome.fromJson(Map<String, dynamic> json) {
    final orgJson = json['organization'] as Map<String, dynamic>?;
    final streamJson = json['stream'] as Map<String, dynamic>?;
    final streams = (json['streams'] as List<dynamic>? ?? [])
        .map((e) => StreamSummary.fromJson(e as Map<String, dynamic>))
        .toList();
    return CreatorHome(
      onboarded: json['onboarded'] as bool? ?? false,
      canBroadcast: json['can_broadcast'] as bool? ?? false,
      organization: orgJson == null ? null : OrgSummary.fromJson(orgJson),
      stream: streamJson == null ? null : StreamSummary.fromJson(streamJson),
      streams: streams,
    );
  }
}

class PublishInfo {
  PublishInfo({
    required this.whipUrl,
    this.hlsUrl,
    this.whepUrl,
    this.stream,
  });

  final String whipUrl;
  final String? hlsUrl;
  final String? whepUrl;
  final StreamSummary? stream;

  factory PublishInfo.fromJson(Map<String, dynamic> json) {
    final streamJson = json['stream'] as Map<String, dynamic>?;
    return PublishInfo(
      whipUrl: json['whip_url'] as String,
      hlsUrl: json['hls_url'] as String?,
      whepUrl: json['whep_url'] as String?,
      stream: streamJson == null ? null : StreamSummary.fromJson(streamJson),
    );
  }
}
