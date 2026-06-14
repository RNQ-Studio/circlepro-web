<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Latihan Bersama — Deep Link / App Link configuration (Sprint 09)
    |--------------------------------------------------------------------------
    |
    | The share artifact is an HTTPS link (https://<APP_URL>/j/{code}) so it is
    | clickable inside WhatsApp. On a device that has the app installed AND has
    | verified the App Links / Universal Links, the OS opens the app directly.
    | Otherwise the link falls back to the public invite landing page.
    */

    // Custom URL scheme fallback used by the landing page's "open in app"
    // button when the HTTPS app link could not open the app (i.e. we are on the
    // web fallback). Mirrors the scheme registered in the Flutter app.
    'scheme' => env('DEEPLINK_SCHEME', 'manahpro'),

    /*
    | Android App Links (Digital Asset Links / assetlinks.json).
    |
    | ⚠️ `sha256_cert_fingerprints` MUST contain the real signing certificate
    | fingerprint(s) from the release keystore for autoVerify to succeed:
    |   keytool -list -v -keystore <release.jks> -alias <alias>
    | Set them via DEEPLINK_ANDROID_SHA256 (comma-separated). Until filled,
    | Android cannot auto-verify and shows a disambiguation chooser instead —
    | the link still opens the app, just not silently.
    */
    'android' => [
        [
            'package_name' => env('DEEPLINK_ANDROID_PACKAGE', 'id.rnq.circlepro'),
            'sha256_cert_fingerprints' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('DEEPLINK_ANDROID_SHA256', ''))
            ))),
        ],
        [
            // Final applicationId once side-by-side field testing ends.
            'package_name' => 'id.rnq.manahpro',
            'sha256_cert_fingerprints' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('DEEPLINK_ANDROID_SHA256', ''))
            ))),
        ],
    ],

    /*
    | iOS Universal Links (apple-app-site-association).
    | appID format = <TeamID>.<BundleID>. ⚠️ Replace TEAMID with the Apple
    | Developer Team ID via DEEPLINK_IOS_APP_IDS (comma-separated).
    */
    'ios' => [
        'app_ids' => array_values(array_filter(array_map('trim', explode(
            ',',
            (string) env('DEEPLINK_IOS_APP_IDS', 'TEAMID.id.rnq.circlepro')
        )))),
    ],

    // Store URLs for the install CTA on the web fallback page.
    'play_store_url' => env(
        'DEEPLINK_PLAY_URL',
        'https://play.google.com/store/apps/details?id=id.rnq.circlepro'
    ),
    'app_store_url' => env('DEEPLINK_APPSTORE_URL', ''),
];
