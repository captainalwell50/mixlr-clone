<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sound Mix Desktop Mixer</title>
    <style>
        html, body { margin: 0; background: #070A09; color: #8A9691; font: 12px/1.4 system-ui, sans-serif; }
        #boot { padding: 8px; }
    </style>
    @vite(['resources/js/desktop-mixer.js'])
</head>
<body>
    <div id="boot">Sound Mix mixer engine</div>
    <audio id="cue-audio" playsinline></audio>
</body>
</html>
