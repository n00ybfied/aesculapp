<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
$sanitizer = new App\Service\RichTextSanitizer();
$cases = [
    ['<p>Hallo <strong>Welt</strong></p>', '<strong>Welt</strong>', true],
    ['<custom><p onclick="evil()">Text<script>alert(1)</script></p></custom>', 'onclick', false],
    ['<custom><p onclick="evil()">Text<script>alert(1)</script></p></custom>', '<script', false],
    ['<a href="javascript:alert(1)">Link</a>', 'javascript:', false],
    ['<a href="https://example.org">Link</a>', 'href="https://example.org"', true],
    ['<img src="data:image/svg+xml,evil" onerror="evil()">', 'data:', false],
    ['<img src="https://example.org/image.jpg" width="400" onerror="evil()">', 'width="400"', true],
    ['<img src="https://example.org/image.jpg" width="400" onerror="evil()">', 'onerror', false],
    ['Bisherige Textbeschreibung', 'Bisherige Textbeschreibung', true],
];
foreach ($cases as [$input, $needle, $expected]) {
    $result = $sanitizer->sanitize($input);
    if (str_contains($result, $needle) !== $expected) { throw new RuntimeException('Sanitizer regression: '.$needle); }
    if ($sanitizer->sanitize($result) !== $result) { throw new RuntimeException('Sanitizer must be idempotent.'); }
}
echo "Rich-text sanitizer: 9 cases passed.\n";
