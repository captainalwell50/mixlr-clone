# Multi-stage build for soundmix/mediamtx:1.15.6-sm1
# Solves bluenviron/mediamtx#6188 (Opus 1.28s WHIP audio reorder stall defect)

FROM golang:alpine AS builder

WORKDIR /src
RUN apk add --no-cache git patch

# Upstream MediaMTX v1.15.6 release
RUN git clone --depth 1 --branch v1.15.6 https://github.com/bluenviron/mediamtx.git .

# Apply WebRTC audio reorder buffer mitigation patch
COPY patches/0001-webrtc-audio-reorder-buffer.patch /tmp/
RUN patch -p1 < /tmp/0001-webrtc-audio-reorder-buffer.patch

# Embed release version and asset placeholders
RUN echo "v1.15.6-sm1" > internal/core/VERSION && \
    echo "/* hls.min.js */" > internal/servers/hls/hls.min.js

# Compile static binary for target architecture
ARG TARGETOS TARGETARCH
RUN CGO_ENABLED=0 GOOS=${TARGETOS:-linux} GOARCH=${TARGETARCH:-amd64} \
    go build -ldflags="-s -w" -o /mediamtx .

# Final stage: official ffmpeg variant + curl + patched binary
FROM bluenviron/mediamtx:1.15.6-ffmpeg

USER root
RUN apk add --no-cache curl

# Replace upstream binary with patched binary
COPY --from=builder /mediamtx /mediamtx

USER root
