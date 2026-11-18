<?php

// Public data subject request form (LGPD/GDPR) and notice e-mail to the operator.
return [
    'types' => [
        'deletion' => 'Delete my photos',
        'access' => 'Get a copy of my photos',
        'correction' => 'Correct data (e.g. name written on the photo)',
        'other' => 'Another request about my data',
    ],
    'form' => [
        'title' => 'Privacy request',
        'intro' => 'Use this form to request access, a copy, correction or deletion of photos you sent at an event, including after it has ended. During the event, you can also do this on the event page itself, under "My photos".',
        'deadlines' => 'We answer within :lgpd days (LGPD) or :gdpr days (GDPR). The request is forwarded to the event organizer, who is the controller of the photos.',
        'type' => 'What would you like to request?',
        'event_link' => 'Event link',
        'event_link_hint' => 'Paste the link of the event page (the address that opened when you scanned the QR code) or write the event name and date.',
        'email' => 'Your contact e-mail',
        'email_hint' => 'Used only to answer this request.',
        'details' => 'Details (optional)',
        'details_hint' => 'Help us find the photo: approximate time of upload, frame used, what the photo shows. Do not send documents.',
        'confirm' => 'I declare that this request is about my own photos (or of someone I am the legal guardian of).',
        'confirm_attribute' => 'declaration',
        'submit' => 'Send request',
        'sent' => 'We have received your request. We will answer at the e-mail provided within the times above.',
        'protocol' => 'Reference: :protocol',
        'policy_link' => 'Read the privacy policy',
    ],
    'mail' => [
        'subject' => 'New privacy request (:protocol)',
        'body' => 'A new data subject request has been recorded (reference :protocol, type: :type). See the details in the admin:',
    ],
];
