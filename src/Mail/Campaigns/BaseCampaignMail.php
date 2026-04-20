<?php

namespace App\Mail\Campaigns;

use App\Framework\Mail\Mailable;
use App\Framework\Support\SiteContext;
use App\Models\Campaign;
use App\Models\Member;

/**
 * Base class for all campaign mailables.
 *
 * Provides:
 *   - member/campaign access for subclasses
 *   - memberFirstName() + siteUrl() helpers
 *   - injectTracking() — wraps the rendered HTML with the open pixel
 *     and rewrites <a href> links to go through CampaignTrackingController
 *
 * Tracking integration (T11):
 *   Every sent email embeds a 1×1 pixel at:
 *     /campaign/track/open/{deliveryToken}
 *
 *   Every <a href="..."> in the rendered body is rewritten to:
 *     /campaign/track/click/{deliveryToken}?url={encodedOriginal}&block={blockKey}
 *
 *   The deliveryToken is set on the mailable by SendCampaignJob AFTER the
 *   CampaignDelivery row is written, so the token always maps to a real row.
 *   If the token is not set (e.g. test send), links pass through unmodified.
 *
 * Block key injection:
 *   Subclasses may tag links with a data-block attribute:
 *     <a href="..." data-block="reward_cta">Claim reward</a>
 *   The link rewriter reads this attribute and passes it as the block param,
 *   enabling block-level click attribution in CampaignEventRepository.
 */
abstract class BaseCampaignMail extends Mailable
{
    /** Set by SendCampaignJob after the CampaignDelivery row is persisted. */
    public ?string $deliveryToken = null;

    public function __construct(
        protected readonly Member   $member,
        protected readonly Campaign $campaign,
    )
    {
        parent::__construct();
    }

    // ── Helpers available to subclasses ──────────────────────────────────

    protected function memberFirstName(): string
    {
        return $this->member->first_name ?? 'there';
    }

    protected function siteUrl(): string
    {
        $site = SiteContext::get();
        return rtrim($site?->url ?? '', '/');
    }

    // ── Tracking injection ────────────────────────────────────────────────

    /**
     * Rewrites a rendered HTML string to inject the open pixel and wrap
     * all links through the tracking controller.
     *
     * Called by SendCampaignJob on the rendered output, not in build().
     * This keeps build() clean and testable without tracking side effects.
     */
    public function injectTracking(string $html): string
    {
        if ($this->deliveryToken === null) {
            return $html;
        }

        $html = $this->injectOpenPixel($html);
        $html = $this->rewriteLinks($html);

        return $html;
    }

    private function injectOpenPixel(string $html): string
    {
        $pixelUrl = $this->siteUrl() . '/campaign/track/open/' . $this->deliveryToken;
        $pixelTag = '<img src="' . htmlspecialchars($pixelUrl) . '" width="1" height="1" '
            . 'style="display:none;width:1px;height:1px;border:0;" alt="">';

        // Insert just before </body> so it doesn't affect layout.
        if (str_contains($html, '</body>')) {
            return str_replace('</body>', $pixelTag . '</body>', $html);
        }

        return $html . $pixelTag;
    }

    private function rewriteLinks(string $html): string
    {
        $token = $this->deliveryToken;
        $siteUrl = $this->siteUrl();

        return preg_replace_callback(
            '/<a\s+([^>]*)href=["\']([^"\']+)["\']([^>]*)>/i',
            function (array $matches) use ($token, $siteUrl): string {
                $before = $matches[1];
                $href = $matches[2];
                $after = $matches[3];

                // Don't rewrite mailto, tel, anchor, or already-tracked links.
                if (preg_match('/^(mailto:|tel:|#|\/campaign\/track)/i', $href)) {
                    return $matches[0];
                }

                // Extract data-block attribute if present.
                $blockKey = null;
                if (preg_match('/data-block=["\']([^"\']+)["\']/i', $before . $after, $blockMatch)) {
                    $blockKey = $blockMatch[1];
                }

                $params = ['url' => $href];
                if ($blockKey !== null) {
                    $params['block'] = $blockKey;
                }

                $trackingUrl = $siteUrl
                    . '/campaign/track/click/' . $token
                    . '?' . http_build_query($params);

                return '<a ' . $before . 'href="' . htmlspecialchars($trackingUrl) . '"' . $after . '>';
            },
            $html
        ) ?? $html;
    }

    public function render(): string
    {
        $html = parent::render();
        return $this->injectTracking($html);
    }
}