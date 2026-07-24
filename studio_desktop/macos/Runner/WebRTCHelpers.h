#import <Foundation/Foundation.h>

NS_ASSUME_NONNULL_BEGIN

@class RTCPeerConnectionFactory;
@protocol RTCAudioCustomProcessingDelegate;

/// Creates a peer-connection factory with optional capture mix injection.
/// Bridged via ObjC because RTCAudioProcessingConfig headers are incomplete in WebRTC-SDK.
RTCPeerConnectionFactory *SMMakePeerConnectionFactory(
    BOOL bypassVoiceProcessing,
    id<RTCAudioCustomProcessingDelegate> _Nullable capturePostProcessingDelegate
);

/// Runs a block, catching NSExceptions (AVAudioEngine can abort via ObjC exceptions).
BOOL SMCatchException(NSError *_Nullable *_Nullable error, void (^block)(void));

NS_ASSUME_NONNULL_END
