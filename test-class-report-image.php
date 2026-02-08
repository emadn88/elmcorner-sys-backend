<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\ClassReportImageService;
use App\Services\WhatsAppService;

try {
    echo "=== Generating test class report image ===\n";
    
    $classReportImageService = app(ClassReportImageService::class);
    $whatsAppService = app(WhatsAppService::class);
    
    // Generate sample class data
    $sampleClass = $classReportImageService->generateSampleClassData();
    
    // Generate the image
    $imageUrl = $classReportImageService->generateClassReportImage($sampleClass, false);
    
    if (!$imageUrl) {
        throw new \Exception("Failed to generate class report image");
    }
    
    echo "Image generated: {$imageUrl}\n";
    
    // Make sure it's a full URL
    if (!filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        $imageUrl = config('app.url') . '/' . ltrim($imageUrl, '/');
    }
    
    echo "Full image URL: {$imageUrl}\n";
    
    // Send to test number
    $testPhone = '+201207220414';
    echo "\n=== Sending image to {$testPhone} ===\n";
    
    $sent = $whatsAppService->sendImage(
        $testPhone,
        $imageUrl,
        'Elm Corner Academy - Test Class Report Image',
        'class_report_image'
    );
    
    if ($sent) {
        echo "✓ Image sent successfully!\n";
    } else {
        echo "✗ Failed to send image\n";
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
    exit(1);
}
