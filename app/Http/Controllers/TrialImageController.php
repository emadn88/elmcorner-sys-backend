<?php

namespace App\Http\Controllers;

use App\Services\TrialImageService;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class TrialImageController extends Controller
{
    protected $trialImageService;

    public function __construct(TrialImageService $trialImageService)
    {
        $this->trialImageService = $trialImageService;
    }

    /**
     * Generate and return trial image with sample data for testing
     * 
     * Public endpoint: GET /test/trial-image
     */
    public function generateTestImage(): Response
    {
        try {
            // Generate sample trial data
            $sampleData = $this->trialImageService->generateSampleTrialData();
            
            // Generate image (return binary)
            $imageBinary = $this->trialImageService->generateTrialImage($sampleData, true);
            
            // Return image with proper headers
            return response($imageBinary, 200)
                ->header('Content-Type', 'image/png')
                ->header('Content-Disposition', 'inline; filename="trial-details.png"')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Pragma', 'no-cache')
                ->header('Expires', '0');
        } catch (\Exception $e) {
            Log::error('Failed to generate test trial image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Return error response
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate trial image: ' . $e->getMessage(),
            ], 500);
        }
    }
}
