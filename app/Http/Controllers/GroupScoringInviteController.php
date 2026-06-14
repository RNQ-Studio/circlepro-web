<?php

namespace App\Http\Controllers;

use App\Models\ScoringSessionGroup;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Public invite landing for Latihan Bersama (group scoring) — Sprint 09.
 *
 * The share artifact is an HTTPS link (https://<host>/j/{code}). On a device
 * with the app installed and verified App Links / Universal Links the OS opens
 * the app directly and this page is never seen. When the app is absent (the
 * deferred deep-link case, task 9.4) this page renders a rich preview of the
 * session plus install CTAs, and stashes the join code on the clipboard so the
 * freshly-installed app can resume to the right session after the user signs up.
 *
 * Also serves the App Links / Universal Links association files so the OS can
 * verify ownership of the domain.
 */
class GroupScoringInviteController extends Controller
{
    /**
     * Render the invite landing for a join code (tasks 9.1/9.2/9.4). Accepts the
     * code as a path segment (/j/{code}) or as a ?code= query string.
     */
    public function show(Request $request, ?string $code = null): View
    {
        $code = Str::upper(trim((string) ($code ?? $request->query('code', ''))));

        $group = $code === ''
            ? null
            : ScoringSessionGroup::query()
                ->with('host')
                ->withCount('participants')
                ->where('join_code', $code)
                ->first();

        return view('group_scoring.invite', [
            'code' => $code,
            'group' => $group,
            'appLink' => $this->appLink($code),
            'schemeLink' => config('deeplink.scheme').'://join?code='.$code,
            'playStoreUrl' => $this->playStoreUrl($code),
            'appStoreUrl' => (string) config('deeplink.app_store_url'),
        ]);
    }

    /**
     * Android App Links verification (Digital Asset Links). Served at the well
     * known path /.well-known/assetlinks.json with an application/json type.
     */
    public function assetLinks(): JsonResponse
    {
        $statements = [];

        foreach ((array) config('deeplink.android', []) as $app) {
            $statements[] = [
                'relation' => ['delegate_permission/common.handle_all_urls'],
                'target' => [
                    'namespace' => 'android_app',
                    'package_name' => $app['package_name'],
                    'sha256_cert_fingerprints' => $app['sha256_cert_fingerprints'],
                ],
            ];
        }

        return response()->json($statements);
    }

    /**
     * iOS Universal Links verification. Served at /.well-known/apple-app-site-
     * association (and the legacy root path) as application/json, no extension.
     */
    public function appleAppSiteAssociation(): JsonResponse
    {
        return response()->json([
            'applinks' => [
                'apps' => [],
                'details' => [[
                    'appIDs' => (array) config('deeplink.ios.app_ids', []),
                    'components' => [
                        ['/' => '/j/*', 'comment' => 'Invite short link'],
                        ['/' => '/group-scoring/join*', 'comment' => 'Invite query link'],
                    ],
                ]],
            ],
        ]);
    }

    private function appLink(string $code): string
    {
        return rtrim((string) config('app.url'), '/').'/j/'.$code;
    }

    /**
     * Carry the join code through the Play Store install as a referrer so an
     * Android client could resume after a fresh install (forward-compatible with
     * the Play Install Referrer API; harmless otherwise).
     */
    private function playStoreUrl(string $code): string
    {
        $base = (string) config('deeplink.play_store_url');
        if ($code === '') {
            return $base;
        }
        $sep = str_contains($base, '?') ? '&' : '?';

        return $base.$sep.'referrer='.urlencode('code='.$code);
    }
}
