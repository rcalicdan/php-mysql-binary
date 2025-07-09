<?php
$testDir = __DIR__ . '/tests';
$files = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($testDir),
    RecursiveIteratorIterator::LEAVES_ONLY
);

foreach ($files as $file) {
    if ($file->getExtension() === 'php') {
        $content = file_get_contents($file->getPathname());
        
        // Rename public function methods to start with test
        $content = preg_replace_callback(
            '/(\s+)(public function )([a-zA-Z][a-zA-Z0-9_]*\([^)]*\)(?:\s*:\s*\w+)?\s*\{)/m',
            function($matches) {
                $indent = $matches[1];
                $functionKeyword = $matches[2];
                $methodSignature = $matches[3];
                
                // Extract method name
                preg_match('/([a-zA-Z][a-zA-Z0-9_]*)\(/', $methodSignature, $nameMatches);
                $methodName = $nameMatches[1];
                
                // Skip if already starts with test, or is setUp/tearDown, or contains Provider
                if (strpos($methodName, 'test') === 0 || 
                    strpos($methodName, 'setUp') === 0 || 
                    strpos($methodName, 'tearDown') === 0 ||
                    strpos($methodName, 'Provider') !== false ||
                    strpos($methodName, 'create') === 0) {
                    return $matches[0];
                }
                
                // Add 'test' prefix
                $newMethodSignature = str_replace($methodName, 'test' . ucfirst($methodName), $methodSignature);
                
                return $indent . $functionKeyword . $newMethodSignature;
            },
            $content
        );
        
        file_put_contents($file->getPathname(), $content);
        echo "Fixed: " . $file->getPathname() . "\n";
    }
}

echo "All test methods have been renamed to start with 'test'\n";