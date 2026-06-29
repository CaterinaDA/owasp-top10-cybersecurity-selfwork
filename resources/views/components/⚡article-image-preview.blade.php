<?php

use Livewire\Component;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

new class extends Component {
    public string $imageUrl = '';
    public string $imageData = '';
    public bool $isLoading = false;

    public function updatedImageUrl()
    {
        $this->imageData = '';
        $this->isLoading = false;

        if (!$this->imageUrl) {
            return;
        }

        $this->isLoading = true;

        try {
            $url = $this->imageUrl;

            // UNSECURE: no host/IP checks -- SSRF vulnerability
            // $response = Http::timeout(10)->get($url);
            // $contentType = $response->header('Content-Type', 'text/plain');
            // $responseBody = $response->body();
            // $this->imageData = 'data:' . $contentType . ';base64,' . base64_encode($responseBody);

            // SECURE

            // 1. URL validation
            $parsed = parse_url($url);
            if (!$parsed || !isset($parsed['scheme'], $parsed['host'])) {
                throw new \InvalidArgumentException('Invalid URL');
            }

            // 2. Only HTTPS allowed
            if (strtolower($parsed['scheme']) !== 'https') {
                throw new \InvalidArgumentException('Only HTTPS URLs are allowed');
            }

            // 3. Resolve IP and block private/localhost addresses
            $ip = gethostbyname($parsed['host']);
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                throw new \InvalidArgumentException('Private/localhost addresses are not allowed');
            }

            // 4. Short timeout, no redirects
            $response = Http::timeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; LaravelApp/1.0)',
                ])
                ->withOptions([
                    'allow_redirects' => false,
                    'verify' => true,
                    'max_redirects' => 0,
                ])
                ->get($url);

            // 5. Content-Type check
            $contentType = $response->header('Content-Type');
            $mimeType = strtolower(trim(explode(';', $contentType)[0]));
            $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            if (!in_array($mimeType, $allowedTypes, true)) {
                throw new \InvalidArgumentException('Content type not allowed');
            }

            // 6. Response size limit (1MB)
            $body = $response->body();
            if (strlen($body) > 1024 * 1024) {
                throw new \InvalidArgumentException('Response too large');
            }

            $this->imageData = 'data:' . $mimeType . ';base64,' . base64_encode($body);
        } catch (\Exception $e) {
            Log::warning('SSRF attempt blocked', [
                'url' => $url,
                'reason' => $e->getMessage(),
                'ip' => request()->ip(),
            ]);
            $this->addError('imageUrl', 'Error: ' . $e->getMessage());
        } finally {
            $this->isLoading = false;
        }
    }
};
?>

<div class="card shadow-sm border-0 p-4 mb-4">
    <h3 class="fw-semibold mb-3">Image Preview by URL</h3>

    <div class="mb-3">
        <label class="form-label fw-semibold">Image URL</label>
        <input type="url" class="form-control" wire:model.live="imageUrl" placeholder="https://example.com/image.jpg">
        @error('imageUrl')
            <div class="alert alert-danger mt-2">{{ $message }}</div>
        @enderror
    </div>

    @if ($isLoading)
        <p class="text-muted">Loading...</p>
    @endif

    @if ($imageData)
        <img src="{{ $imageData }}" class="img-fluid rounded" alt="Preview">
    @endif
</div>
