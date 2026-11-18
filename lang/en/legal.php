<?php

// Privacy policy and terms of use (LGPD + GDPR). Template text: must be reviewed by a lawyer
// before commercial use (see README, "Privacidade (LGPD/GDPR)"). Same placeholders as pt_BR.
return [
    'missing' => '[to be completed]',
    'version' => 'Version :version',
    'back' => 'Back to the home page',
    'public_note' => 'Template text pending legal review.',
    'admin_banner' => 'This text is a template and must be reviewed by a lawyer before commercial use.',
    'admin_missing' => 'Privacy fields still to be completed in the admin: :fields.',
    'admin_settings_link' => 'Complete now',
    'on_this_page' => 'On this page',
    'transfer_default' => 'Data is stored on the hosting servers listed in section 5. If the contracted region is outside the data subject\'s country (for example, servers in the European Union for data subjects in Brazil, or the other way round), this transfer is necessary to provide the service.',

    'cookies_table' => [
        'name' => 'Name',
        'purpose' => 'Purpose',
        'duration' => 'Duration',
        'rows' => [
            ['name' => ':session_cookie', 'purpose' => 'Session for the organizer dashboard and the guest page (login, language, form protection).', 'duration' => ':session_minutes minutes of inactivity'],
            ['name' => 'XSRF-TOKEN', 'purpose' => 'Protection against cross-site request forgery (CSRF).', 'duration' => ':session_minutes minutes'],
            ['name' => 'remember_web_…', 'purpose' => 'Keeps the organizer signed in, only if they tick "Remember me".', 'duration' => 'up to 400 days or until sign-out'],
            ['name' => 'pp_device', 'purpose' => 'Anonymous random identifier of the guest\'s device, used to limit uploads and to show "My photos". The server stores only a hash.', 'duration' => ':cookie_days days'],
            ['name' => 'pp_consent_v1:… (local storage)', 'purpose' => 'Remembers, on the device itself, that the guest has read and accepted this version of the notice.', 'duration' => 'until the guest clears the browser data'],
        ],
    ],

    'privacy' => [
        'title' => 'Privacy policy',
        'intro' => 'This policy explains how Photo Party processes personal data, in accordance with the Brazilian General Data Protection Law (LGPD, Law 13,709/2018) and the EU General Data Protection Regulation (GDPR, Regulation (EU) 2016/679).',
        'sections' => [
            [
                'id' => 'who-we-are',
                'heading' => '1. Who we are and how to contact us',
                'paragraphs' => [
                    'Photo Party is operated by :company (registration: :company_id; address: :address).',
                    'Privacy contact: :contact. Data protection officer (DPO / encarregado): :dpo (:dpo_email).',
                ],
            ],
            [
                'id' => 'roles',
                'heading' => '2. Roles: controller and processor',
                'paragraphs' => [
                    'Photos and data of an event\'s guests: the controller is the event organizer (the account that created the event, whose name appears in the notice on the guest page). The organizer decides what the photos are used for. Photo Party is the processor (operador under the LGPD): it processes those photos only to provide the service to the organizer and according to the organizer\'s instructions.',
                    'Data of organizer accounts (sign-up, login, dashboard use): Photo Party is the controller.',
                ],
            ],
            [
                'id' => 'data',
                'heading' => '3. Data we process',
                'items' => [
                    'Guests: the photo sent (already composed with frame and filter), its thumbnail, the optional name and texts the guest types on the frame, date and time of upload, the version of the notice accepted and an anonymous device identifier (hash of a random cookie). We do not ask for e-mail, phone or an account. Image metadata (EXIF, including GPS location) is removed on the server.',
                    'Organizers: name, e-mail, password (stored only as a hash), account and event data, uploaded frames and audit records of dashboard actions (including IP address).',
                    'Data subject requests: event link, contact e-mail and the description of the request.',
                    'Technical records: IP address and browser in the session and in security logs, for the periods in section 7.',
                    'We do not intentionally process sensitive data and we do not use facial recognition, biometrics or profiling. A photo may show characteristics of a person; it is processed only as an event picture.',
                ],
            ],
            [
                'id' => 'purposes',
                'heading' => '4. Purposes and legal bases',
                'items' => [
                    'Showing the photo on the event screen and delivering it to the organizer (gallery and download): the guest\'s consent (LGPD art. 7, I; GDPR art. 6(1)(a)), collected on the event page before the first upload.',
                    'Providing the service to the organizer (account, events, moderation, export): performance of a contract (LGPD art. 7, V; GDPR art. 6(1)(b)).',
                    'Security, abuse prevention and auditing (upload limits, access logs): legitimate interests and legal obligation (LGPD art. 7, II and IX; GDPR art. 6(1)(c) and (f)).',
                    'Handling data subject requests: legal obligation (LGPD art. 18; GDPR arts. 12 to 22).',
                ],
            ],
            [
                'id' => 'sharing',
                'heading' => '5. Sharing and sub-processors',
                'paragraphs' => [
                    'We do not sell data or share it with third parties for advertising. We do not use analytics services, ad networks, CDNs, external fonts or third-party captcha.',
                    'The photos of an event are seen by the organizer and their team and shown on the event screen. Each organizer can access only their own events.',
                    'Sub-processor: website and database hosting is provided by Hostinger, with servers in :region. Transactional e-mail (when enabled) uses the same host\'s mail server.',
                    'We may disclose data to authorities when required by law.',
                ],
            ],
            [
                'id' => 'transfers',
                'heading' => '6. International transfers',
                'paragraphs' => [
                    ':transfer',
                    'Where data is transferred outside Brazil or the European Economic Area, the transfer relies on the mechanisms of the LGPD (art. 33) and the GDPR (chapter V), such as adequacy decisions or standard contractual clauses.',
                ],
            ],
            [
                'id' => 'retention',
                'heading' => '7. How long we keep data',
                'items' => [
                    'Photos, thumbnails, previews and ZIP files of an event: until the end of the retention period of the organizer\'s plan, counted from the end of the event (for an ended free trial without a subscription, :trial_days days after the end of the trial period). After that, a daily process deletes the files and keeps only the minimal event record (name and dates) in the organizer dashboard.',
                    'Photo deleted by the guest: files are deleted immediately; a technical record with no personal data exists for a few hours only to remove it from the screen.',
                    'Guest device cookie: :cookie_days days.',
                    'IP address in audit records: :ip_days days; after that the IP is erased.',
                    'Organizer account data: while the account exists. When the account is deleted, everything is erased (database and files), except an audit record with no personal data.',
                    'Data subject requests: for as long as needed to prove they were handled.',
                ],
            ],
            [
                'id' => 'rights',
                'heading' => '8. Your rights',
                'paragraphs' => [
                    'Under the LGPD (art. 18) you may request: confirmation that processing exists; access; correction; anonymisation, blocking or deletion of unnecessary or excessive data; portability; deletion of data processed on the basis of consent; information about sharing; information about the possibility of not consenting; and withdrawal of consent.',
                    'Under the GDPR (arts. 15 to 22) you have the right of access, rectification, erasure, restriction of processing, data portability, objection and not to be subject to solely automated decisions (we make no automated decisions or profiling). Withdrawing consent does not affect processing carried out before the withdrawal.',
                ],
            ],
            [
                'id' => 'exercise',
                'heading' => '9. How to exercise your rights and response times',
                'items' => [
                    'During the event, on the event page itself, under "My photos": view, download in original size and delete the photos sent from your device. Deleting a photo withdraws consent and removes it from the screen.',
                    'After the event or from another device: use the form at :request_url, giving the event link and a contact e-mail. As guests have no account, we may ask for information to confirm the photo is yours before delivering or deleting it.',
                    'Organizers: under "My account", export your own data (JSON) and delete the account with all its data.',
                    'Response times: up to :lgpd_days days (LGPD) and up to :gdpr_days days (GDPR), extendable where the law allows. As a processor, we forward requests about photos of an organizer\'s events to that organizer (the controller) and help them respond.',
                ],
            ],
            [
                'id' => 'authority',
                'heading' => '10. Supervisory authority',
                'paragraphs' => [
                    'If you are not satisfied with our answer, you can complain to the Brazilian National Data Protection Authority (ANPD) or to the supervisory authority of your country in the European Union (in Ireland, the Data Protection Commission).',
                ],
            ],
            [
                'id' => 'security',
                'heading' => '11. Security',
                'paragraphs' => [
                    'Always-encrypted connections (HTTPS), photos stored outside the public folder and delivered only to those with permission, isolation between organizers, temporary screen links, hashed passwords, rate limiting, removal of image metadata and audit logs. No measure is infallible, which is why we keep the incident plan in section 13.',
                ],
            ],
            [
                'id' => 'children',
                'heading' => '12. Children and teenagers',
                'paragraphs' => [
                    'Events may have underage participants. The organizer, as controller, is responsible for obtaining the authorisation of parents or guardians to collect and show images of minors (LGPD art. 14; GDPR art. 8) and declares this when creating each event. Photo Party does not verify age. Parents or guardians can request deletion of a photo using the form in section 9.',
                ],
            ],
            [
                'id' => 'incidents',
                'heading' => '13. Security incidents',
                'paragraphs' => [
                    'If an incident occurs that may cause relevant risk or harm, we will notify the affected organizers (controllers) without undue delay and, where we are the controller or where the law requires, the ANPD and/or the competent supervisory authority (under the GDPR, within 72 hours) and the affected data subjects.',
                ],
            ],
            [
                'id' => 'cookies',
                'heading' => '14. Cookies',
                'paragraphs' => [
                    'We use only cookies that are strictly necessary for the service to work. That is why there is no cookie consent banner. We do not use advertising, analytics or third-party cookies.',
                ],
                'cookies' => true,
            ],
            [
                'id' => 'changes',
                'heading' => '15. Changes to this policy',
                'paragraphs' => [
                    'This policy has a version and a date. When it changes materially, the new version is published here and the notice on the guest page asks for a new acceptance; organizers are informed in the dashboard or by e-mail.',
                ],
            ],
        ],
    ],

    'terms' => [
        'title' => 'Terms of use',
        'intro' => 'These terms govern the use of Photo Party by event organizers (account holders) and by guests who send photos.',
        'sections' => [
            [
                'id' => 'service',
                'heading' => '1. The service',
                'paragraphs' => [
                    'Photo Party, operated by :company (:company_id, :address), lets organizers create events where guests send photos from their browser (via QR code) to be shown on a screen, with moderation and a gallery for the organizer.',
                ],
            ],
            [
                'id' => 'account',
                'heading' => '2. Organizer account',
                'paragraphs' => [
                    'The organizer must provide accurate data, keep the password secret and is responsible for what happens in the account and for the team members they invite. One free trial account per person.',
                ],
            ],
            [
                'id' => 'plans',
                'heading' => '3. Plans, free trial and cancellation',
                'paragraphs' => [
                    'Limits (events, photos, retention, custom frames) depend on the plan. During the free trial the organizer sees only low-resolution previews; original-size downloads and ZIP export are available on paid plans. Plans are activated by the administration while there is no online payment. The organizer can delete the account at any time under "My account".',
                ],
            ],
            [
                'id' => 'acceptable-use',
                'heading' => '4. Acceptable use and moderation',
                'paragraphs' => [
                    'It is forbidden to use the service for illegal, offensive or discriminatory content, content that infringes image rights or copyright, or that exposes minors inappropriately. The organizer moderates what goes on the screen and is responsible for the content shown at their event. We may suspend accounts that breach these terms.',
                ],
            ],
            [
                'id' => 'guests',
                'heading' => '5. Guests',
                'paragraphs' => [
                    'Guests send photos voluntarily, after reading the event privacy notice, and declare they have the right to send them. They can view, download and delete their own photos under "My photos" while the event is active, and request access or deletion later using the privacy form.',
                ],
            ],
            [
                'id' => 'dpa',
                'heading' => '6. Processing on behalf of the organizer (data processing agreement)',
                'paragraphs' => [
                    'Regarding the photos and data of the guests of their events, the organizer is the controller and Photo Party is the processor (art. 28 GDPR; operador under the LGPD). By accepting these terms, the parties agree that:',
                ],
                'items' => [
                    'Instructions: Photo Party processes this data only to provide the service described (receive, store, moderate, show on screen, provide gallery and export) and on the organizer\'s documented instructions, which are the settings the organizer makes in the dashboard.',
                    'Legal basis and minors: the organizer warrants it has a legal basis for the processing, keeps the notice to guests and declares, when creating each event, that it is authorised to collect images of participants, including from the guardians of minors.',
                    'Confidentiality: persons authorised to access this data are bound by confidentiality. Administration access to photos is exceptional (support or legal requirement) and recorded in the audit log.',
                    'Security: appropriate technical and organisational measures, described in the privacy policy (isolation between organizers, encryption in transit, access control, auditing, metadata removal).',
                    'Sub-processors: the organizer authorises Hostinger as hosting provider (servers in :region). Changes of sub-processor will be announced with reasonable notice, and the organizer may object by closing the account.',
                    'International transfers: as in section 6 of the privacy policy.',
                    'Assistance: Photo Party helps the organizer respond to data subject requests ("My photos", photo deletion, export and forwarding of requests received through the form) and comply with security and incident notification obligations.',
                    'Incidents: Photo Party will notify the organizer without undue delay after becoming aware of an incident affecting the data of their events, with the information available.',
                    'Deletion at the end: at the end of the plan\'s retention period or when the account is deleted, event files and data are erased, unless the law requires them to be kept.',
                    'Demonstrating compliance: Photo Party makes available the information needed to demonstrate compliance with these obligations and cooperates with reasonable, pre-agreed audits.',
                ],
            ],
            [
                'id' => 'ownership',
                'heading' => '7. Ownership of the photos',
                'paragraphs' => [
                    'Photos belong to whoever took them and/or to the people pictured, as the law provides. Photo Party acquires no rights over them and uses them only to provide the service.',
                ],
            ],
            [
                'id' => 'liability',
                'heading' => '8. Availability and liability',
                'paragraphs' => [
                    'We aim to keep the service available, but it may be interrupted. We recommend that organizers download the photos they want to keep before the retention period ends. To the extent permitted by law, we are not liable for indirect damages; nothing in these terms limits consumer rights that cannot be limited.',
                ],
            ],
            [
                'id' => 'changes',
                'heading' => '9. Changes and governing law',
                'paragraphs' => [
                    'These terms have a version and a date and may be updated; material changes will be announced in the dashboard. Contact: :contact. Governing law and jurisdiction: [to be defined with the lawyer according to the market (Brazil and/or European Union)].',
                ],
            ],
        ],
    ],
];
