<?php

return [
    'max_image_kb' => (int) env('UPLOAD_MAX_IMAGE_KB', 5120),
    'max_design_kb' => (int) env('UPLOAD_MAX_DESIGN_KB', 20480),
    'public_disk' => env('UPLOAD_PUBLIC_DISK', 'public'),
    'private_disk' => env('UPLOAD_PRIVATE_DISK', 'local'),
    'image_extensions' => ['jpg','jpeg','png','webp'],
    'image_mimes' => ['image/jpeg','image/png','image/webp'],
    'design_extensions' => ['jpg','jpeg','png','webp','pdf','psd','ai'],
    'design_mimes' => ['image/jpeg','image/png','image/webp','application/pdf','image/vnd.adobe.photoshop','application/postscript','application/octet-stream'],
    'preview_minutes' => (int) env('UPLOAD_PREVIEW_MINUTES', 15),
];
