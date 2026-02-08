<?php

namespace App\Http\Controllers;

use App\Services\ClassReportImageService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class ClassReportImageController extends Controller
{
    protected $classReportImageService;

    public function __construct(ClassReportImageService $classReportImageService)
    {
        $this->classReportImageService = $classReportImageService;
    }

    /**
     * Generate and return class report image with sample data for testing
     * 
     * Public endpoint: GET /test/class-report-image
     */
    public function generateTestImage(): Response
    {
        try {
            // Generate sample class data
            $sampleData = $this->classReportImageService->generateSampleClassData();
            
            // Generate image (return binary)
            $imageBinary = $this->classReportImageService->generateClassReportImage($sampleData, true);
            
            // Return image with proper headers
            return response($imageBinary, 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'inline; filename="class-report.png"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Exception $e) {
            Log::error('Failed to generate test class report image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return error response
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate class report image: ' . $e->getMessage(),
            ], 500);
        }
    }
}
