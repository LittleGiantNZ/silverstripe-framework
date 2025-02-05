<?php

namespace SilverStripe\Core\Tests;

use SilverStripe\Dev\SapphireTest;

/**
 * Test the syntax of the PHP files with various settings
 */
class PhpSyntaxTest extends SapphireTest
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('This needs to be written to include only core php files, not test/thirdparty files');
    }

    public function testShortTagsOffWillWork()
    {
        // Ignore this test completely if running the test suite on windows
        // TODO: Make it work on all platforms, by building an alternative to find | grep.
        $returnCode = 0;
        $output = [];
        exec("which find && which grep && which php", $output, $returnCode);
        if ($returnCode != 0) {
            $this->markTestSkipped("Only works on *nix based systems");
            return;
        }

        $settingTests = ['short_open_tag=Off','short_open_tag=On -d asp_tags=On'];

        $files = $this->getAllFiles('php');
        $files[] = FRAMEWORK_PATH . '/src/Dev/Install/config-form.html';

        foreach ($files as $i => $file) {
            $CLI_file = escapeshellarg($file);
            foreach ($settingTests as $settingTest) {
                $returnCode = 0;
                $output = [];
                exec("php -l -d $settingTest $CLI_file", $output, $returnCode);
                $hasErrors = ($returnCode != 0
                    && strpos('No syntax errors detected', implode("\n", $output)) === false);
                $this->assertFalse(
                    $hasErrors,
                    "Syntax error parsing $CLI_file with setting $settingTest:\n"
                    . implode("\n", $output) . " (Returned: {$returnCode})"
                );
            }
        }
    }

    public function getAllFiles($ext = 'php')
    {
        // TODO: Unix only
        $cmd = sprintf(
            'find %s | grep %s',
            BASE_PATH,
            escapeshellarg(".{$ext}\$")
        );
        return explode("\n", trim(`$cmd`));
    }
}
