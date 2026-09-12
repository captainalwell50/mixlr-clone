#ifndef NDI_SDK_SHIM_H
#define NDI_SDK_SHIM_H

#include <stdbool.h>
#include <stdint.h>

#ifdef __cplusplus
extern "C" {
#endif

typedef struct SmlNdiSource {
    const char *p_ndi_name;
    const char *p_url_address;
} SmlNdiSource;

typedef struct SmlNdiFindCreate {
    bool show_local_sources;
    const char *p_groups;
    const char *p_extra_ips;
} SmlNdiFindCreate;

typedef struct SmlNdiRecvCreate {
    SmlNdiSource source_to_connect_to;
    int color_format;
    int bandwidth;
    bool allow_video_fields;
    const char *p_ndi_recv_name;
} SmlNdiRecvCreate;

typedef struct SmlNdiVideoFrame {
    int xres;
    int yres;
    int fourCC;
    int frame_rate_N;
    int frame_rate_D;
    float picture_aspect_ratio;
    int frame_format_type;
    int64_t timecode;
    uint8_t *p_data;
    int line_stride_in_bytes;
    const char *p_metadata;
    int64_t timestamp;
} SmlNdiVideoFrame;

enum {
    SmlNdiColorBgrxBgra = 0,
    SmlNdiBandwidthHighest = 100,
    SmlNdiFrameNone = 0,
    SmlNdiFrameVideo = 1
};

#ifdef __cplusplus
}
#endif

#endif
