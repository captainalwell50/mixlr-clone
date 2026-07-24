#import "WebRTCHelpers.h"
#import <WebRTC/WebRTC.h>

/// Header is missing from WebRTC-SDK; class exists at runtime (LiveKit build).
@interface RTCAudioProcessingConfig : NSObject
@property(nonatomic, assign) BOOL echoCancellerEnabled;
@property(nonatomic, assign) BOOL echoCancellerMobileMode;
@end

RTCPeerConnectionFactory *SMMakePeerConnectionFactory(
    BOOL bypassVoiceProcessing,
    id<RTCAudioCustomProcessingDelegate> _Nullable capturePostProcessingDelegate
) {
  // Keep APM so capturePostProcessingDelegate (mix inject) runs, but turn off
  // echo cancellation — default voice processing makes music sound muffled.
  RTCAudioProcessingConfig *config = nil;
  Class configClass = NSClassFromString(@"RTCAudioProcessingConfig");
  if (configClass != Nil) {
    config = [[configClass alloc] init];
    if ([config respondsToSelector:@selector(setEchoCancellerEnabled:)]) {
      config.echoCancellerEnabled = NO;
    }
    if ([config respondsToSelector:@selector(setEchoCancellerMobileMode:)]) {
      config.echoCancellerMobileMode = NO;
    }
  }

  RTCDefaultAudioProcessingModule *apm =
      [[RTCDefaultAudioProcessingModule alloc] initWithConfig:config
                                capturePostProcessingDelegate:capturePostProcessingDelegate
                                  renderPreProcessingDelegate:nil];
  return [[RTCPeerConnectionFactory alloc]
      initWithBypassVoiceProcessing:bypassVoiceProcessing
                     encoderFactory:[[RTCDefaultVideoEncoderFactory alloc] init]
                     decoderFactory:[[RTCDefaultVideoDecoderFactory alloc] init]
              audioProcessingModule:apm];
}

BOOL SMCatchException(NSError *_Nullable *_Nullable error, void (^block)(void)) {
  @try {
    block();
    return YES;
  } @catch (NSException *exception) {
    if (error != NULL) {
      *error = [NSError errorWithDomain:@"NativeAudio"
                                   code:1
                               userInfo:@{
                                 NSLocalizedDescriptionKey :
                                     (exception.reason ?: @"Audio engine connection failed"),
                               }];
    }
    return NO;
  }
}
