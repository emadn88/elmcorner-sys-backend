<?php

namespace App\Http\Controllers\API\Admin;

use App\Http\Controllers\Controller;
use App\Services\BillingService;
use App\Models\Bill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BillingController extends Controller
{
    protected $billingService;

    public function __construct(BillingService $billingService)
    {
        $this->billingService = $billingService;
    }

    /**
     * List bills with filters
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['year', 'month', 'status', 'student_id', 'teacher_id', 'is_custom']);
        
        // Default to current month if not specified
        if (!isset($filters['year']) || !isset($filters['month'])) {
            $filters['year'] = now()->year;
            $filters['month'] = now()->month;
        }

        $bills = $this->billingService->getBills($filters);

        // Get statistics for current month (with same filters)
        $statistics = $this->billingService->getBillingStatistics(
            $filters['year'],
            $filters['month'],
            $filters
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'bills' => $bills,
                'statistics' => $statistics,
            ],
        ]);
    }

    /**
     * Get billing statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'required|integer|min:1|max:12',
        ]);

        $statistics = $this->billingService->getBillingStatistics(
            $request->input('year'),
            $request->input('month')
        );

        return response()->json([
            'status' => 'success',
            'data' => $statistics,
        ]);
    }

    /**
     * Get single bill with details
     */
    public function show(string $id): JsonResponse
    {
        $bill = Bill::with([
            'student.family',
            'teacher.user',
            'package',
        ])->findOrFail($id);

        // Get classes included in this bill
        $classes = [];
        if ($bill->class_ids && is_array($bill->class_ids)) {
            $classes = \App\Models\ClassInstance::with(['teacher.user', 'course'])
                ->whereIn('id', $bill->class_ids)
                ->orderBy('class_date', 'asc')
                ->orderBy('start_time', 'asc')
                ->get();
        } elseif ($bill->class_id) {
            $class = \App\Models\ClassInstance::with(['teacher.user', 'course'])->find($bill->class_id);
            if ($class) {
                $classes = collect([$class]);
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'bill' => $bill,
                'classes' => $classes,
            ],
        ]);
    }

    /**
     * Create custom bill
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'nullable|exists:students,id',
            'amount' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:3',
            'bill_date' => 'nullable|date',
            'description' => 'nullable|string|max:1000',
            'teacher_id' => 'nullable|exists:teachers,id',
            'package_id' => 'nullable|exists:packages,id',
        ]);

        try {
            $bill = $this->billingService->createCustomBill($request->all());

            return response()->json([
                'status' => 'success',
                'message' => 'Custom bill created successfully',
                'data' => $bill->load(['student', 'teacher.user', 'package']),
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Mark bill as paid
     */
    public function markAsPaid(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'payment_method' => 'required|string|max:255',
            'payment_date' => 'nullable|date',
            'payment_reason' => 'required|string|max:1000',
        ]);

        try {
            $bill = $this->billingService->markAsPaid(
                (int)$id,
                $request->input('payment_method'),
                $request->input('payment_date'),
                $request->input('payment_reason')
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bill marked as paid successfully',
                'data' => $bill->load(['student', 'teacher.user', 'package']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Send bill via WhatsApp directly to student
     */
    public function sendWhatsApp(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'whatsapp_number' => 'nullable|string|max:20',
        ]);

        try {
            $whatsappNumber = $request->input('whatsapp_number');
            $success = $this->billingService->sendBillViaWhatsApp((int)$id, $whatsappNumber);

            if ($success) {
            return response()->json([
                'status' => 'success',
                    'message' => 'Bill sent via WhatsApp successfully',
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Failed to send WhatsApp message',
                ], 400);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Download bill PDF
     */
    public function downloadPdf(string $id)
    {
        try {
            $pdfPath = $this->billingService->generateBillPDF((int)$id);

            if (!Storage::exists($pdfPath)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'PDF file not found',
                ], 404);
            }

            return Storage::download($pdfPath, 'bill_' . $id . '.pdf');
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate payment token
     */
    public function generateToken(string $id): JsonResponse
    {
        try {
            $token = $this->billingService->generatePaymentToken((int)$id);
            $bill = Bill::findOrFail($id);

            // Extract just the 5-character suffix for the URL
            $tokenSuffix = str_replace('elmcorner', '', $token);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Payment token generated successfully',
                'data' => [
                    'token' => $token,
                    'token_suffix' => $tokenSuffix,
                    'payment_url' => url("/payment/{$tokenSuffix}"),
                ],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
