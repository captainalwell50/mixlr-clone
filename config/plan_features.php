<?php

/**
 * Canonical feature catalog for pricing packages.
 * All capabilities exist in the product; each Plan selects which are enabled
 * (and numeric quotas) via the `plans.limits` JSON column.
 *
 * type: bool | int
 * group: used for admin UI sections
 */
return [
    'quotas' => [
        'max_streams' => [
            'label' => 'Live streams / channels',
            'type' => 'int',
            'default' => 1,
            'min' => 0,
            'max' => 1000,
            'help' => 'How many studio streams this plan may create.',
        ],
        'max_recordings' => [
            'label' => 'Podcasts or uploads',
            'type' => 'int',
            'default' => 20,
            'min' => 0,
            'max' => 100000,
            'help' => 'Max public podcasts + studio library uploads kept on platform storage.',
        ],
        'storage_bytes' => [
            'label' => 'Platform storage (GB)',
            'type' => 'storage_gb',
            'default' => 2,
            'min' => 0,
            'max' => 10240,
            'help' => 'Hosted recordings + library. Google Drive files do not count.',
        ],
    ],

    'features' => [
        'live_streaming' => [
            'label' => 'Unlimited live streaming',
            'group' => 'Broadcast',
            'default' => true,
        ],
        'channel_page' => [
            'label' => 'Persistent channel page (schedule + archive)',
            'group' => 'Channel',
            'default' => true,
        ],
        'followable_profile' => [
            'label' => 'Followable public profile',
            'group' => 'Channel',
            'default' => true,
        ],
        'listener_notifications' => [
            'label' => 'Listener notifications',
            'group' => 'Channel',
            'default' => true,
        ],
        'gallery' => [
            'label' => 'Live gallery & video reels',
            'group' => 'Channel',
            'default' => true,
        ],
        'embed_player' => [
            'label' => 'Customizable embed player',
            'group' => 'Player',
            'default' => true,
        ],
        'embed_whitelist' => [
            'label' => 'Controlled embed access (domain whitelist)',
            'group' => 'Player',
            'default' => false,
        ],
        'listener_analytics' => [
            'label' => 'Detailed listener analytics',
            'group' => 'Insights',
            'default' => false,
        ],
        'private_streams' => [
            'label' => 'Private streams with access controls',
            'group' => 'Access',
            'default' => false,
        ],
        'donations' => [
            'label' => 'Donations / support link',
            'group' => 'Monetization',
            'default' => false,
        ],
        'stream_fallback' => [
            'label' => 'Automatic fallback for stream reliability',
            'group' => 'Broadcast',
            'default' => false,
        ],
        'advanced_scheduling' => [
            'label' => 'Advanced scheduling and automation',
            'group' => 'Broadcast',
            'default' => false,
        ],
        'rtmp_ingest' => [
            'label' => 'Stream key access (RTMP ingest / OBS)',
            'group' => 'Broadcast',
            'default' => false,
        ],
        'radio_directory' => [
            'label' => 'Radio directory compatibility (live stream URL)',
            'group' => 'Broadcast',
            'default' => false,
        ],
        'priority_support' => [
            'label' => 'Priority support',
            'group' => 'Support',
            'default' => false,
        ],
        'multi_channel' => [
            'label' => 'Multi-channel / network support',
            'group' => 'Enterprise',
            'default' => false,
        ],
        'api_access' => [
            'label' => 'Developer access: API & SDK',
            'group' => 'Enterprise',
            'default' => false,
        ],
        'branded_apps' => [
            'label' => 'Custom-branded listener apps',
            'group' => 'Enterprise',
            'default' => false,
        ],
        'account_manager' => [
            'label' => 'Account manager + onboarding',
            'group' => 'Enterprise',
            'default' => false,
        ],
    ],
];
