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
      id: _asInt(json['id']) ?? 0,
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
      id: _asInt(json['id']) ?? 0,
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

class EventSummary {
  EventSummary({
    required this.id,
    required this.status,
    this.uuid,
    this.title,
    this.url,
  });

  final int id;
  final String status;
  final String? uuid;
  final String? title;
  final String? url;

  bool get isOpen =>
      status == 'live' || status == 'paused' || status == 'scheduled';

  factory EventSummary.fromJson(Map<String, dynamic> json) {
    return EventSummary(
      id: _asInt(json['id']) ?? 0,
      uuid: json['uuid'] as String?,
      title: json['title'] as String?,
      status: json['status'] as String? ?? 'scheduled',
      url: json['url'] as String?,
    );
  }

  EventSummary copyWithStatus(String status) {
    return EventSummary(
      id: id,
      uuid: uuid,
      title: title,
      status: status,
      url: url,
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

class DisplaySongItem {
  DisplaySongItem({
    required this.id,
    required this.title,
    required this.slides,
    required this.slideCount,
  });

  final int id;
  final String title;
  final List<String> slides;
  final int slideCount;

  factory DisplaySongItem.fromJson(Map<String, dynamic> json) {
    final rawSlides = json['slides'];
    final slides = rawSlides is List
        ? rawSlides
            .map((e) => e.toString())
            .where((e) => e.trim().isNotEmpty)
            .toList()
        : <String>[];
    return DisplaySongItem(
      id: _asInt(json['id']) ?? 0,
      title: json['title'] as String? ?? '',
      slides: slides,
      slideCount: _asInt(json['slide_count']) ?? slides.length,
    );
  }
}

class SongCue {
  SongCue({
    required this.title,
    required this.text,
    this.id,
    this.slideIndex = 0,
    this.slideCount = 1,
    this.updatedAt,
  });

  final int? id;
  final String title;
  final String text;
  final int slideIndex;
  final int slideCount;
  final String? updatedAt;

  factory SongCue.fromJson(Map<String, dynamic> json) {
    return SongCue(
      id: _asInt(json['id']),
      title: json['title'] as String? ?? '',
      text: json['text'] as String? ?? '',
      slideIndex: _asInt(json['slide_index']) ?? 0,
      slideCount: _asInt(json['slide_count']) ?? 1,
      updatedAt: json['updated_at'] as String?,
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

  StreamSummary copyWith({String? status, String? listenUrl}) {
    return StreamSummary(
      uuid: uuid,
      title: title,
      status: status ?? this.status,
      description: description,
      isPublic: isPublic,
      chatEnabled: chatEnabled,
      listenUrl: listenUrl ?? this.listenUrl,
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
    this.openEvent,
    this.needsUpgrade = false,
    this.billingUrl,
  });

  final bool onboarded;
  final bool canBroadcast;
  final OrgSummary? organization;
  final StreamSummary? stream;
  final List<StreamSummary> streams;
  final EventSummary? openEvent;
  final bool needsUpgrade;
  final String? billingUrl;

  factory CreatorHome.fromJson(Map<String, dynamic> json) {
    final orgJson = json['organization'] as Map<String, dynamic>?;
    final streamJson = json['stream'] as Map<String, dynamic>?;
    final eventJson = json['open_event'] as Map<String, dynamic>?;
    final streams = (json['streams'] as List<dynamic>? ?? [])
        .map((e) => StreamSummary.fromJson(e as Map<String, dynamic>))
        .toList();
    return CreatorHome(
      onboarded: json['onboarded'] as bool? ?? false,
      canBroadcast: json['can_broadcast'] as bool? ?? false,
      organization: orgJson == null ? null : OrgSummary.fromJson(orgJson),
      stream: streamJson == null ? null : StreamSummary.fromJson(streamJson),
      streams: streams,
      openEvent: eventJson == null ? null : EventSummary.fromJson(eventJson),
      needsUpgrade: json['needs_upgrade'] as bool? ?? false,
      billingUrl: json['billing_url'] as String?,
    );
  }
}

class PublishInfo {
  PublishInfo({
    required this.whipUrl,
    this.hlsUrl,
    this.whepUrl,
    this.stream,
    this.event,
  });

  final String whipUrl;
  final String? hlsUrl;
  final String? whepUrl;
  final StreamSummary? stream;
  final EventSummary? event;

  factory PublishInfo.fromJson(Map<String, dynamic> json) {
    final streamJson = json['stream'] as Map<String, dynamic>?;
    final eventJson = json['event'] as Map<String, dynamic>?;
    return PublishInfo(
      whipUrl: json['whip_url'] as String? ?? '',
      hlsUrl: json['hls_url'] as String?,
      whepUrl: json['whep_url'] as String?,
      stream: streamJson == null
          ? null
          : StreamSummary.fromJson(streamJson),
      event: eventJson == null ? null : EventSummary.fromJson(eventJson),
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
      id: _asInt(json['id']) ?? 0,
      title: json['title'] as String? ?? 'Untitled',
      url: json['url'] as String? ?? '',
      originalFilename: json['original_filename'] as String?,
      mimeType: json['mime_type'] as String?,
      sizeBytes: _asInt(json['size_bytes']),
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
      assetId: _asInt(json['assetId']),
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
      id: _asInt(json['id']) ?? 0,
      url: json['url'] as String? ?? '',
      type: json['type'] as String? ?? 'image',
      caption: json['caption'] as String?,
      posterUrl: json['poster_url'] as String?,
      durationSeconds: (json['duration_seconds'] as num?)?.round(),
    );
  }
}

int? _asInt(dynamic value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  if (value is String) return int.tryParse(value);
  return null;
}
