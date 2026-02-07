<?php

namespace App\Services;

use App\Models\TrialClass;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class TrialImageService
{
    /**
     * Generate a modern trial details image
     * 
     * @param TrialClass|array $trialData TrialClass model or array of trial data
     * @return string Path to generated image or binary image data
     */
    public function generateTrialImage($trialData, bool $returnBinary = false)
    {
        try {
            // Handle model instance or array
            if ($trialData instanceof TrialClass) {
                // Load student relationship if not already loaded
                if (!$trialData->relationLoaded('student')) {
                    $trialData->load('student');
                }
                $trial = $trialData;
            } else {
                // Convert array to object if needed
                $trial = is_array($trialData) ? (object) $trialData : $trialData;
            }
            
            // Get background image as base64 for Browsershot
            $backgroundPath = public_path('report_background.png');
            $backgroundBase64 = null;
            
            if (file_exists($backgroundPath)) {
                $imageData = file_get_contents($backgroundPath);
                $imageInfo = getimagesize($backgroundPath);
                $mimeType = $imageInfo['mime'] ?? 'image/png';
                $backgroundBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);
            }
            
            // Get logo as base64 for Browsershot
            $logoPath = public_path('logo.png');
            $logoBase64 = null;
            
            if (file_exists($logoPath)) {
                $logoData = file_get_contents($logoPath);
                $logoInfo = getimagesize($logoPath);
                $logoMimeType = $logoInfo['mime'] ?? 'image/png';
                $logoBase64 = 'data:' . $logoMimeType . ';base64,' . base64_encode($logoData);
            } else {
                // Log if logo not found for debugging
                Log::warning('Logo file not found', ['path' => $logoPath]);
            }
            
            // Determine template based on student language
            $template = 'trial-image'; // Default to Arabic
            if (isset($trial->student)) {
                $student = is_object($trial->student) ? $trial->student : (object)$trial->student;
                $language = $student->language ?? 'ar';
                
                switch ($language) {
                    case 'en':
                        $template = 'trial-image-en';
                        break;
                    case 'fr':
                        $template = 'trial-image-fr';
                        break;
                    case 'ar':
                    default:
                        $template = 'trial-image';
                        break;
                }
            } elseif (isset($trial->student_id) && $trialData instanceof TrialClass) {
                // If student relationship not loaded, try to get language from student model
                try {
                    $student = \App\Models\Student::find($trial->student_id);
                    if ($student && $student->language) {
                        switch ($student->language) {
                            case 'en':
                                $template = 'trial-image-en';
                                break;
                            case 'fr':
                                $template = 'trial-image-fr';
                                break;
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback to default Arabic template
                }
            }
            
            // Render HTML template
            $html = View::make($template, [
                'trial' => $trial,
                'backgroundImage' => $backgroundBase64,
                'logoImage' => $logoBase64,
            ])->render();

            // Generate image using Browsershot
            $image = Browsershot::html($html)
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox'])
                ->windowSize(1080, 1350)
                ->deviceScaleFactor(2) // High DPI for better quality
                ->waitUntilNetworkIdle()
                ->screenshot();

            if ($returnBinary) {
                return $image;
            }

            // Save to storage
            $filename = 'trials/trial_' . ($trial->id ?? time()) . '_' . time() . '.png';
            Storage::disk('public')->put($filename, $image);

            return Storage::disk('public')->url($filename);
        } catch (\Exception $e) {
            Log::error('Failed to generate trial image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate sample trial data for testing
     */
    public function generateSampleTrialData(): array
    {
        return [
            'id' => 999,
            'status' => 'pending',
            'trial_date' => now()->addDays(2)->format('Y-m-d'),
            'start_time' => '14:00:00',
            'end_time' => '15:00:00',
            'notes' => 'This is a sample trial class. Please arrive on time and bring your materials.',
            'student' => (object) [
                'full_name' => 'Ahmed Mohamed',
                'email' => 'ahmed.mohamed@example.com',
                'whatsapp' => '+201234567890',
                'country' => 'Egypt',
                'language' => 'ar',
            ],
            'teacher' => (object) [
                'user' => (object) [
                    'name' => 'Dr. Sarah Johnson',
                ],
            ],
            'course' => (object) [
                'name' => 'Advanced English Conversation',
            ],
            'created_at' => now()->subDays(1)->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }
}
