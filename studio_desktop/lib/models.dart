import 'config.dart';

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
    this.channelUrl,
    this.canBroadcast = false,
    this.creatorType,
  });

  final int id;
  final String name;
  final String slug;
  final String? themeColor;
  final String? artworkUrl;
  final String? channelUrl;
  final bool canBroadcast;
  final String? creatorType;

  bool get isChurch => creatorType == 'church';

  String get publicChannelUrl {
    if (channelUrl != null && channelUrl!.isNotEmpty) return channelUrl!;
    return '${AppConfig.apiBase}/c/$slug';
  }

  factory OrgSummary.fromJson(Map<String, dynamic> json) {
    return OrgSummary(
      id: json['id'] as int,
      name: json['name'] as String? ?? '',
      slug: json['slug'] as String? ?? '',
      themeColor: json['theme_color'] as String?,
      artworkUrl: json['artwork_url'] as String?,
      channelUrl: json['channel_url'] as String?,
      canBroadcast: json['can_broadcast'] as bool? ?? false,
      creatorType: json['creator_type'] as String?,
    );
  }
}

class ScriptureCue {
  ScriptureCue({required this.ref, required this.text, this.updatedAt});

  final String ref;
  final String text;
  final String? updatedAt;

  factory ScriptureCue.fromJson(Map<String, dynamic> json) {
    return ScriptureCue(
      ref: json['ref'] as String? ?? '',
      text: json['text'] as String? ?? '',
      updatedAt: json['updated_at'] as String?,
    );
  }
}

class ScriptureSuggestion {
  ScriptureSuggestion({required this.ref, required this.preview});

  final String ref;
  final String preview;

  factory ScriptureSuggestion.fromJson(Map<String, dynamic> json) {
    return ScriptureSuggestion(
      ref: json['ref'] as String? ?? '',
      preview: json['preview'] as String? ?? '',
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

class LibraryAsset {
  LibraryAsset({
    required this.id,
    required this.title,
    required this.url,
    this.originalFilename,
    this.mimeType,
    this.sizeBytes,
    this.durationSeconds,
  });

  final int id;
  final String title;
  final String url;
  final String? originalFilename;
  final String? mimeType;
  final int? sizeBytes;
  final int? durationSeconds;

  factory LibraryAsset.fromJson(Map<String, dynamic> json) {
    return LibraryAsset(
      id: json['id'] as int,
      title: json['title'] as String? ?? 'Untitled',
      url: json['url'] as String? ?? '',
      originalFilename: json['original_filename'] as String?,
      mimeType: json['mime_type'] as String?,
      sizeBytes: json['size_bytes'] as int?,
      durationSeconds: (json['duration_seconds'] as num?)?.round(),
    );
  }
}

class MixerTrack {
  MixerTrack({
    required this.id,
    required this.title,
    this.assetId,
    this.ready = false,
    this.playing = false,
    this.currentTime = 0,
    this.duration = 0,
  });

  final String id;
  final String title;
  final int? assetId;
  final bool ready;
  final bool playing;
  final double currentTime;
  final double duration;

  factory MixerTrack.fromJson(Map<String, dynamic> json) {
    return MixerTrack(
      id: json['id'] as String,
      title: json['title'] as String? ?? 'Audio',
      assetId: json['assetId'] as int?,
      ready: json['ready'] as bool? ?? false,
      playing: json['playing'] as bool? ?? false,
      currentTime: (json['currentTime'] as num?)?.toDouble() ?? 0,
      duration: (json['duration'] as num?)?.toDouble() ?? 0,
    );
  }
}

class AudioInputDevice {
  AudioInputDevice({required this.deviceId, required this.label});

  final String deviceId;
  final String label;

  factory AudioInputDevice.fromJson(Map<String, dynamic> json) {
    return AudioInputDevice(
      deviceId: json['deviceId'] as String? ?? '',
      label: json['label'] as String? ?? 'Microphone',
    );
  }
}

class GalleryItem {
  GalleryItem({
    required this.id,
    required this.url,
    required this.type,
    this.caption,
    this.posterUrl,
    this.durationSeconds,
  });

  final int id;
  final String url;
  final String type;
  final String? caption;
  final String? posterUrl;
  final int? durationSeconds;

  bool get isVideo => type == 'video';

  factory GalleryItem.fromJson(Map<String, dynamic> json) {
    return GalleryItem(
      id: json['id'] as int,
      url: json['url'] as String? ?? '',
      type: json['type'] as String? ?? 'image',
      caption: json['caption'] as String?,
      posterUrl: json['poster_url'] as String?,
      durationSeconds: (json['duration_seconds'] as num?)?.round(),
    );
  }
}
