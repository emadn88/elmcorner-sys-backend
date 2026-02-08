<?php

namespace App\Services;

use App\Models\ClassInstance;
use Illuminate\Support\Facades\View;
use Spatie\Browsershot\Browsershot;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ClassReportImageService
{
    /**
     * Generate a professional class report image
     * 
     * @param ClassInstance|array $classData ClassInstance model or array of class data
     * @return string Path to generated image or binary image data
     */
    public function generateClassReportImage($classData, bool $returnBinary = false)
    {
        try {
            // Handle model instance or array
            if ($classData instanceof ClassInstance) {
                // Load relationships if not already loaded
                if (!$classData->relationLoaded('student')) {
                    $classData->load('student');
                }
                if (!$classData->relationLoaded('teacher')) {
                    $classData->load('teacher.user');
                }
                if (!$classData->relationLoaded('course')) {
                    $classData->load('course');
                }
                $class = $classData;
            } else {
                // Convert array to object if needed
                $class = is_array($classData) ? (object) $classData : $classData;
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
                Log::warning('Logo file not found', ['path' => $logoPath]);
            }
            
            // Determine template based on student language
            $template = 'class-report-image'; // Default to Arabic
            if (isset($class->student)) {
                $student = is_object($class->student) ? $class->student : (object)$class->student;
                $language = $student->language ?? 'ar';
                
                switch ($language) {
                    case 'en':
                        $template = 'class-report-image-en';
                        break;
                    case 'fr':
                        $template = 'class-report-image-fr';
                        break;
                    case 'ar':
                    default:
                        $template = 'class-report-image';
                        break;
                }
            } elseif (isset($class->student_id) && $classData instanceof ClassInstance) {
                try {
                    $student = \App\Models\Student::find($class->student_id);
                    if ($student && $student->language) {
                        switch ($student->language) {
                            case 'en':
                                $template = 'class-report-image-en';
                                break;
                            case 'fr':
                                $template = 'class-report-image-fr';
                                break;
                        }
                    }
                } catch (\Exception $e) {
                    // Fallback to default Arabic template
                }
            }
            
            // Render HTML template
            $html = View::make($template, [
                'class' => $class,
                'backgroundImage' => $backgroundBase64,
                'logoImage' => $logoBase64,
            ])->render();

            // Generate image using Browsershot
            $image = Browsershot::html($html)
                ->setChromePath('/usr/bin/google-chrome-stable')
                ->setOption('args', ['--no-sandbox', '--disable-setuid-sandbox', '--disable-gpu', '--disable-dev-shm-usage'])
                ->windowSize(1080, 1350)
                ->deviceScaleFactor(1) // Reduced quality for smaller file size
                ->waitUntilNetworkIdle()
                ->screenshot();

            if ($returnBinary) {
                return $image;
            }

            // Save to storage
            $filename = 'class-reports/class_' . ($class->id ?? time()) . '_' . time() . '.png';
            Storage::disk('public')->put($filename, $image);

            return Storage::disk('public')->url($filename);
        } catch (\Exception $e) {
            Log::error('Failed to generate class report image', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate sample class data for testing
     */
    public function generateSampleClassData(): array
    {
        return [
            'id' => 888,
            'status' => 'attended',
            'class_date' => now()->subDays(1)->format('Y-m-d'),
            'start_time' => now()->subDays(1)->setTime(14, 0, 0)->format('H:i:s'),
            'end_time' => now()->subDays(1)->setTime(15, 0, 0)->format('H:i:s'),
            'duration' => 60,
            'class_report' => 'Excellent class today! The student showed great progress in conversation skills. We covered advanced grammar topics and practiced speaking exercises. The student was very engaged and asked insightful questions.',
            'student_evaluation' => 'Excellent',
            'notes' => 'Student needs to practice more on past tense. Recommended additional reading materials.',
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
            'created_at' => now()->subDays(2)->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }
}
