<?php

namespace Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use Illuminate\View\Compilers\BladeCompiler;
use PHPUnit\Framework\TestCase;

class PanelHeadAssetsTest extends TestCase
{
    /** @dataProvider layoutAssets */
    public function test_head_assets_render_after_an_inline_php_directive($layout, $assets, $styles, $scripts)
    {
        $source = file_get_contents(__DIR__ . '/../../resources/views/layouts/' . $layout . '/app.blade.php');
        preg_match('/(?:<\?php|@php)\s*\$panelHeadAssets\s*=[\s\S]*?(?:\?>|@endphp)/', $source, $initialization);
        preg_match('/\{!! preg_replace\([^\n]*\$panelHeadAssets\) !!\}/', $source, $headOutput);
        preg_match('/@foreach \(\$panelHeadScripts\[0\] as \$panelHeadScript\)[\s\S]*?@endforeach/', $source, $bodyOutput);
        $this->assertNotEmpty($initialization);
        $this->assertNotEmpty($headOutput);
        $this->assertNotEmpty($bodyOutput);

        // Both real layouts have an inline @php($logo = ...) before this block.
        // This compiler incorrectly pairs that directive with a later @endphp.
        $template = "@php(\$logo = 'icon.png')\n" . $initialization[0]
            . '<head>' . $headOutput[0] . '</head><body>' . $bodyOutput[0] . '</body>';
        $compiler = new BladeCompiler(new Filesystem(), sys_get_temp_dir());
        $compiled = $compiler->compileString($template);
        $this->assertStringNotContainsString('@php', $compiled);

        $__env = new class($assets) {
            use \Illuminate\View\Concerns\ManagesLoops;

            private $assets;

            public function __construct($assets)
            {
                $this->assets = $assets;
            }

            public function yieldPushContent($stack)
            {
                return $stack === 'css_or_js' ? $this->assets : '';
            }
        };

        ob_start();
        try {
            eval('?>' . $compiled);
            $output = ob_get_contents();
        } finally {
            ob_end_clean();
        }

        $this->assertStringContainsString('<head>' . $styles . '</head>', $output);
        preg_match('/<body>([\s\S]*)<\/body>/', $output, $body);
        $this->assertSame($scripts, preg_replace('/>\s+</', '><', trim($body[1])));
    }

    public function layoutAssets()
    {
        foreach (['vendor', 'admin'] as $layout) {
            yield $layout . ' empty stack' => [$layout, '', '', ''];
            yield $layout . ' styles only' => [$layout, '<style>.page { color: red; }</style>', '<style>.page { color: red; }</style>', ''];
            yield $layout . ' styles and scripts' => [
                $layout,
                '<link rel="stylesheet" href="page.css"><script src="page.js"></script><script>window.pageReady = true;</script>',
                '<link rel="stylesheet" href="page.css">',
                '<script src="page.js"></script><script>window.pageReady = true;</script>',
            ];
        }
    }
}
