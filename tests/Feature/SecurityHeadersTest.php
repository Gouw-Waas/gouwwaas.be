<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_send_security_headers(): void
    {
        $response = $this->get('/')->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeaderMissing('Strict-Transport-Security'); // only over https

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("default-src 'self'", $csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
        $this->assertStringContainsString("object-src 'none'", $csp);
        $this->assertStringContainsString('https://geo.api.vlaanderen.be', $csp);       // map tiles
        $this->assertStringContainsString('https://nominatim.openstreetmap.org', $csp); // address search
        $this->assertStringContainsString("form-action 'self';", $csp); // only the pay page posts elsewhere
        $this->assertStringNotContainsString("script-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_inline_scripts_carry_this_requests_nonce(): void
    {
        $first = $this->get('/districten')->assertOk();
        preg_match("/'nonce-([^']+)'/", $first->headers->get('Content-Security-Policy'), $match);
        $nonce = $match[1];

        $this->assertStringContainsString('<script nonce="'.$nonce.'">window.districtMap', $first->getContent());

        // A new nonce for every request.
        preg_match("/'nonce-([^']+)'/", $this->get('/districten')->headers->get('Content-Security-Policy'), $second);
        $this->assertNotSame($nonce, $second[1]);
    }

    public function test_hsts_over_https(): void
    {
        $this->get('https://localhost/')
            ->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        $this->assertStringContainsString('upgrade-insecure-requests', $this->get('https://localhost/')->headers->get('Content-Security-Policy'));
    }

    /**
     * The CSP blocks inline scripts without a nonce and inline event handlers: make sure none
     * sneak into the views (they would silently stop working in the browser).
     */
    public function test_views_have_no_inline_scripts_without_nonce_or_inline_handlers(): void
    {
        foreach (File::allFiles(resource_path('views')) as $file) {
            $html = $file->getContents();

            $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\b(src|nonce)=)[^>]*>/i', $html, "Inline <script> without nonce in {$file->getRelativePathname()}");
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+\s*=\s*["\']/i', $html, "Inline event handler in {$file->getRelativePathname()}");
        }
    }
}
