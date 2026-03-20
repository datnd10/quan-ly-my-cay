<?php
/**
 * Generate OpenAPI documentation from annotations
 * 
 * Run: php generate-openapi.php
 */

// Nếu có composer autoload
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
    
    $openapi = \OpenApi\Generator::scan([__DIR__ . '/src/app/controllers']);
    
    // Save to file
    file_put_contents(__DIR__ . '/src/docs/openapi.json', $openapi->toJson());
    
    echo "✅ OpenAPI documentation generated successfully!\n";
    echo "📄 File: src/docs/openapi.json\n";
} else {
    // Fallback: Manual generation without swagger-php
    echo "⚠️  Composer not installed. Using manual OpenAPI template.\n";
    echo "💡 To use auto-generation, run: composer install\n";
}
