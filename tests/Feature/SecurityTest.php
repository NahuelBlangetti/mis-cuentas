<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_páginas_envían_headers_de_seguridad(): void
    {
        $response = $this->get(route('login'))->assertOk();

        $this->assertStringContainsString("script-src 'self'", $response->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString("frame-ancestors 'none'", $response->headers->get('Content-Security-Policy'));
        $response->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY')->assertHeader('Referrer-Policy', 'same-origin');
    }

    public function test_las_páginas_privadas_no_se_guardan_en_caché(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_hsts_solo_se_envía_por_https(): void
    {
        $this->get(route('login'))->assertHeaderMissing('Strict-Transport-Security');
        $this->get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }

    public function test_las_vistas_no_tienen_javascript_inline(): void
    {
        foreach ([...glob(resource_path('views/*.blade.php')), ...glob(resource_path('views/*/*.blade.php'))] as $view) {
            $this->assertDoesNotMatchRegularExpression('/\son[a-z]+=|<script(?![^>]*\ssrc=)/i', file_get_contents($view), basename($view).' tiene JS inline que la CSP bloquearía');
        }
    }
}
