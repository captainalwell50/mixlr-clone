#import "WebRTCHelpers.h"
#import <WebRTC/WebRTC.h>

/// Header is missing from WebRTC-SDK; class exists at runtime (LiveKit build).
@interface RTCAudioProcessingConfig : NSObject
@property(nonatomic, assign) BOOL echoCancellerEnabled;
@property(nonatomic, assign) BOOL echoCancellerMobileMode;
@end

static void SMDisableAudioProcessingKey(id config, NSString *key) {
  @try {
    [config setValue:@NO forKey:key];
  } @catch (NSException *exception) {
    // Property not present on this WebRTC-SDK build.
  }
}

RTCPeerConnectionFactory *SMMakePeerConnectionFactory(
    BOOL bypassVoiceProcessing,
    id<RTCAudioCustomProcessingDelegate> _Nullable capturePostProcessingDelegate
) {
  // Keep APM so capturePostProcessingDelegate (mix inject) runs, but turn off
  // voice processing — NS/AGC/HPF make music sound muffled / telephone-like.
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
    for (NSString *key in @[
           @"noiseSuppressorEnabled",
           @"noiseSuppressionEnabled",
           @"highPassFilterEnabled",
           @"autoGainControl1Enabled",
           @"autoGainControl2Enabled",
           @"transientSuppressorEnabled",
           @"residualEchoDetectorEnabled",
         ]) {
      SMDisableAudioProcessingKey(config, key);
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
