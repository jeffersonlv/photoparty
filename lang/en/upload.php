<?php

// Modules 3-4: guest page (server side) and photo upload.
return [
    'page' => [
        'starts_at' => 'This event starts on :date. Come back then to send your photo.',
        'ended' => 'This event has ended.',
        'not_published' => 'This event has not been published yet. As soon as the organizer publishes it, you can send your photo using this same link.',
        'date_format' => 'M j, Y \a\t g:i A',
        'frame_date_format' => 'M j, Y',
    ],

    'attributes' => [
        'photo' => 'photo',
        'thumb' => 'thumbnail',
        'guest_name' => 'name',
    ],

    'errors' => [
        'event_not_live' => 'This event is not accepting photos right now.',
        'event_limit' => 'This event has reached its photo limit.',
        'guest_limit' => 'You have already sent the maximum number of photos for this event.',
        'storage' => 'We could not save your photo right now (storage unavailable). Please try again later.',
        'not_image' => 'the file is not a valid JPEG, PNG or WebP image.',
        'photo_dimensions' => 'the photo must be 1080 × 1350 px (portrait) or 1920 × 1080 px (landscape).',
        'thumb_dimensions' => 'the thumbnail must be at most 400 px on its longest side and match the photo orientation.',
        'frame' => 'this frame is not available for this event.',
        'texts_format' => 'the texts were sent in an invalid format.',
        'texts_unknown' => 'this frame does not accept that text.',
        'texts_length' => 'each text can have at most :max characters.',
        'texts_html' => 'texts cannot contain HTML or control characters.',
        'consent' => 'To send the photo, confirm that you have read and agree to the privacy notice.',
        'consent_version' => 'The privacy notice has changed. Reload the page, read it and accept it again.',
    ],
];
