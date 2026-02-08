<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\ClassInstance;
use App\Models\Student;
use App\Models\Package;
use App\Models\Teacher;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BillingService
{
    protected $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Create or update incremental bill for package
     * Bills accumulate lessons from a package automatically
     */
    public function createBillForClass(ClassInstance $class): Bill
    {
        // Only create bills for attended or absent_student classes
        if (!in_array($class->status, ['attended', 'absent_student'])) {
            throw new \Exception('Cannot create bill for class with status: ' . $class->status);
        }

        // Check if package has existing pending bill
        $existingBill = Bill::where('package_id', $class->package_id)
            ->where('student_id', $class->student_id)
            ->where('status', 'pending')
            ->where('is_custom', false)
            ->first();

        // Get package to use student's hour_price instead of teacher's hourly_rate
        $package = Package::findOrFail($class->package_id);
        $student = Student::findOrFail($class->student_id);
        $teacher = Teacher::findOrFail($class->teacher_id);
        $durationHours = $class->duration / 60.0;
        // Use package hour_price if available, otherwise fall back to teacher hourly_rate
        $hourPrice = $package->hour_price ?? 0;
        if ($hourPrice <= 0) {
            $hourPrice = $teacher->hourly_rate ?? 0;
        }
        $amount = $durationHours * $hourPrice;
        
        // Use package currency, fall back to student currency, then USD
        $currency = $package->currency ?? $student->currency ?? 'USD';

        if ($existingBill) {
            // Add class to existing bill
            $classIds = $existingBill->class_ids ?? [];
            if (!in_array($class->id, $classIds)) {
                $classIds[] = $class->id;
                $existingBill->class_ids = $classIds;
                $existingBill->total_hours = ($existingBill->total_hours ?? 0) + $durationHours;
                $existingBill->amount = $existingBill->amount + $amount;
                // Update currency if not set or if it's different (should match package/student)
                if (!$existingBill->currency || $existingBill->currency !== $currency) {
                    $existingBill->currency = $currency;
                }
                $existingBill->save();
            }
            return $existingBill->fresh();
        } else {
            // Create new bill with first class
            return Bill::create([
                'package_id' => $class->package_id,
                'class_id' => $class->id,
                'student_id' => $class->student_id,
                'teacher_id' => $class->teacher_id,
                'class_ids' => [$class->id],
                'duration' => $class->duration,
                'total_hours' => $durationHours,
                'amount' => $amount,
                'currency' => $currency,
                'status' => 'pending',
                'bill_date' => $class->class_date,
                'is_custom' => false,
            ]);
        }
    }

    /**
     * Create custom bill (not linked to classes)
     */
    public function createCustomBill(array $data): Bill
    {
        $student = null;
        if (isset($data['student_id']) && $data['student_id']) {
            $student = Student::findOrFail($data['student_id']);
        }

        return Bill::create([
            'student_id' => $data['student_id'] ?? null,
            'teacher_id' => $data['teacher_id'] ?? null,
            'package_id' => $data['package_id'] ?? null,
            'amount' => $data['amount'],
            'currency' => $data['currency'] ?? ($student ? $student->currency : 'USD'),
            'status' => 'pending',
            'bill_date' => $data['bill_date'] ?? now()->toDateString(),
            'description' => $data['description'] ?? null,
            'is_custom' => true,
            'duration' => 0,
            'total_hours' => 0,
        ]);
    }

    /**
     * Get bills grouped by month
     */
    public function getBillsByMonth(int $year, int $month, array $filters = []): array
    {
        $query = Bill::with(['student', 'teacher.user', 'package'])
            ->whereYear('bill_date', $year)
            ->whereMonth('bill_date', $month);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        $bills = $query->orderBy('bill_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Group by status
        $paid = $bills->where('status', 'paid')->values();
        $unpaid = $bills->whereIn('status', ['pending', 'sent'])->values();

        return [
            'paid' => $paid,
            'unpaid' => $unpaid,
            'all' => $bills,
        ];
    }

    /**
     * Get billing statistics for a month
     */
    public function getBillingStatistics(int $year, int $month, array $filters = []): array
    {
        $startDate = Carbon::create($year, $month, 1)->startOfMonth();
        $endDate = Carbon::create($year, $month, 1)->endOfMonth();

        $query = Bill::whereBetween('bill_date', [$startDate, $endDate]);
        
        // Apply is_custom filter if provided
        if (isset($filters['is_custom'])) {
            $isCustom = $filters['is_custom'];
            if (is_string($isCustom)) {
                $isCustom = filter_var($isCustom, FILTER_VALIDATE_BOOLEAN);
            }
            $query->where('is_custom', (bool)$isCustom);
        }
        
        $bills = $query->get();

        $dueBills = $bills->whereIn('status', ['pending', 'sent']);
        $paidBills = $bills->where('status', 'paid');

        // Group by currency for accurate totals
        $dueByCurrency = $dueBills->groupBy('currency');
        $paidByCurrency = $paidBills->groupBy('currency');

        $dueTotal = [];
        $paidTotal = [];

        foreach ($dueByCurrency as $currency => $currencyBills) {
            $dueTotal[$currency] = $currencyBills->sum('amount');
        }

        foreach ($paidByCurrency as $currency => $currencyBills) {
            $paidTotal[$currency] = $currencyBills->sum('amount');
        }

        return [
            'due' => [
                'total' => $dueTotal,
                'count' => $dueBills->count(),
            ],
            'paid' => [
                'total' => $paidTotal,
                'count' => $paidBills->count(),
            ],
            'unpaid' => [
                'total' => $dueTotal,
                'count' => $dueBills->count(),
            ],
        ];
    }

    /**
     * Mark bill as paid
     */
    public function markAsPaid(int $billId, string $paymentMethod, ?string $paymentDate = null, ?string $paymentReason = null, ?string $paypalTransactionId = null): Bill
    {
        $bill = Bill::findOrFail($billId);

        $updateData = [
            'status' => 'paid',
            'payment_method' => $paymentMethod,
            'payment_date' => $paymentDate ? Carbon::parse($paymentDate) : now(),
            'payment_reason' => $paymentReason,
        ];

        if ($paypalTransactionId) {
            $updateData['paypal_transaction_id'] = $paypalTransactionId;
        }

        $bill->update($updateData);

        return $bill->fresh();
    }

    /**
     * Generate payment token for public access
     * Format: elmcorner + 5 alphanumeric characters (e.g., elmcorner4Hkvm)
     */
    public function generatePaymentToken(int $billId): string
    {
        $bill = Bill::findOrFail($billId);

        if ($bill->payment_token) {
            return $bill->payment_token;
        }

        // Generate 5-character alphanumeric token
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $tokenSuffix = '';
        
        do {
            $tokenSuffix = '';
            for ($i = 0; $i < 5; $i++) {
                $tokenSuffix .= $characters[random_int(0, strlen($characters) - 1)];
            }
            $token = 'elmcorner' . $tokenSuffix;
        } while (Bill::where('payment_token', $token)->exists());

        $bill->update(['payment_token' => $token]);

        return $token;
    }

    /**
     * Get bill by token for public payment page
     * Accepts either full token (elmcornerXXXXX) or just the suffix (XXXXX)
     */
    public function getBillByToken(string $token): Bill
    {
        // If token doesn't start with 'elmcorner', prepend it
        if (!str_starts_with($token, 'elmcorner')) {
            $token = 'elmcorner' . $token;
        }

        $bill = Bill::with([
            'student.family',
            'teacher.user',
            'package',
        ])->where('payment_token', $token)->firstOrFail();

        return $bill;
    }

    /**
     * Send bill via WhatsApp directly to student
     */
    public function sendBillViaWhatsApp(int $billId, ?string $whatsappNumber = null): bool
    {
        $bill = Bill::with('student')->findOrFail($billId);

        // Determine WhatsApp number
        $phone = null;
        if ($whatsappNumber) {
            // Use provided WhatsApp number
            $phone = $whatsappNumber;
        } elseif ($bill->student && $bill->student->whatsapp) {
            // Use student's WhatsApp number
            $phone = $bill->student->whatsapp;
        } else {
            throw new \Exception('No WhatsApp number available. Please provide a WhatsApp number.');
        }

        // Generate payment token if not exists
        if (!$bill->payment_token) {
            $this->generatePaymentToken($billId);
            $bill->refresh();
        }

        // Generate payment link - extract just the 5-character suffix
        $tokenSuffix = str_replace('elmcorner', '', $bill->payment_token);
        $paymentUrl = url("/payment/{$tokenSuffix}");
        
        // Get student language (default to English, only use French if student is French)
        $studentLanguage = 'en'; // Default to English
        if ($bill->student) {
            $studentLanguage = strtolower(trim($bill->student->language ?? 'en'));
            if (!in_array($studentLanguage, ['en', 'fr'])) {
                $studentLanguage = 'en'; // Default to English
            }
        }

        // Format message based on bill type
        $message = $bill->is_custom 
            ? $this->formatCustomBillWhatsAppMessage($bill, $paymentUrl, $studentLanguage)
            : $this->formatAutoBillWhatsAppMessage($bill, $paymentUrl, $studentLanguage);

        // Send directly via WhatsApp service
        // Remove any non-digit characters except +
        $cleanPhone = preg_replace('/[^\d+]/', '', $phone);
        if (!str_starts_with($cleanPhone, '+')) {
            $cleanPhone = '+' . $cleanPhone;
        }

        $success = $this->whatsAppService->sendMessage($cleanPhone, $message);

        if ($success) {
        // Update bill status and sent_at
        $bill->update([
            'status' => 'sent',
            'sent_at' => now(),
        ]);
        }

        return $success;
    }

    /**
     * Format WhatsApp message for custom bill (manual/advance payment)
     */
    protected function formatCustomBillWhatsAppMessage(Bill $bill, string $paymentUrl, string $language = 'en'): string
    {
        $studentName = $bill->student ? $bill->student->full_name : 'Customer';
        $amount = number_format($bill->amount, 2);
        $currency = $bill->currency;
        $invoiceDate = $bill->bill_date->format('Y-m-d');
        $reason = $bill->description ?? '';
        $academyName = 'ElmCorner Academy';

        // Normalize language - default to English, only use French if student is French
        $language = strtolower(trim($language));
        if (!in_array($language, ['en', 'fr'])) {
            $language = 'en';
        }

        if ($language === 'fr') {
            // French template
            $message = "🎓 *{$academyName}*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📋 *Facture Manuelle*\n";
            $message .= "Étudiant: *{$studentName}*\n";
            $message .= "Date d'émission: *{$invoiceDate}*\n";
            $message .= "Montant: *{$amount} {$currency}*\n";
            if ($reason) {
                $message .= "Raison: *{$reason}*\n";
            }
            $message .= "\n💳 *Payer en toute sécurité:*\n";
            $message .= "{$paymentUrl}\n\n";
            $message .= "Merci d'avoir choisi {$academyName}! 🌟";
        } else {
            // English template (default)
            $message = "🎓 *{$academyName}*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📋 *Manual Invoice*\n";
            $message .= "Student: *{$studentName}*\n";
            $message .= "Issue Date: *{$invoiceDate}*\n";
            $message .= "Amount: *{$amount} {$currency}*\n";
            if ($reason) {
                $message .= "Reason: *{$reason}*\n";
            }
            $message .= "\n💳 *Pay Securely:*\n";
            $message .= "{$paymentUrl}\n\n";
            $message .= "Thank you for choosing {$academyName}! 🌟";
        }

        return $message;
    }

    /**
     * Format WhatsApp message for auto bill (automatic invoice)
     */
    protected function formatAutoBillWhatsAppMessage(Bill $bill, string $paymentUrl, string $language = 'en'): string
    {
        $studentName = $bill->student->full_name;
        $amount = number_format($bill->amount, 2);
        $currency = $bill->currency;
        $invoiceDate = $bill->bill_date->format('Y-m-d');
        $invoiceNumber = '#' . str_pad($bill->id, 6, '0', STR_PAD_LEFT);
        $academyName = 'ElmCorner Academy';

        // Normalize language - default to English, only use French if student is French
        $language = strtolower(trim($language));
        if (!in_array($language, ['en', 'fr'])) {
            $language = 'en';
        }

        if ($language === 'fr') {
            // French template
            $message = "🎓 *{$academyName}*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📋 *Facture Auto*\n";
            $message .= "Étudiant: *{$studentName}*\n";
            $message .= "Numéro de facture: *{$invoiceNumber}*\n";
            $message .= "Date d'émission: *{$invoiceDate}*\n";
            $message .= "Montant: *{$amount} {$currency}*\n\n";
            $message .= "💳 *Payer en toute sécurité:*\n";
            $message .= "{$paymentUrl}\n\n";
            $message .= "Merci d'avoir choisi {$academyName}! 🌟";
        } else {
            // English template (default)
            $message = "🎓 *{$academyName}*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📋 *Auto Invoice*\n";
            $message .= "Student: *{$studentName}*\n";
            $message .= "Invoice Number: *{$invoiceNumber}*\n";
            $message .= "Issue Date: *{$invoiceDate}*\n";
            $message .= "Amount: *{$amount} {$currency}*\n\n";
            $message .= "💳 *Pay Securely:*\n";
            $message .= "{$paymentUrl}\n\n";
            $message .= "Thank you for choosing {$academyName}! 🌟";
        }

        return $message;
    }

    /**
     * Format WhatsApp message for package bills with language support
     */
    public function formatPackageBillWhatsAppMessage(Package $package, $bills, array $billsSummary, string $language = 'ar'): string
    {
        $studentName = $package->student->full_name;
        $totalAmount = number_format($billsSummary['total_amount'], 2);
        $currency = $billsSummary['currency'];
        $totalHours = number_format($billsSummary['total_hours'], 2);
        
        // Get date range from package
        $startDate = $package->start_date ? Carbon::parse($package->start_date)->format('Y-m-d') : '';
        $endDate = $package->updated_at ? Carbon::parse($package->updated_at)->format('Y-m-d') : '';
        
        // Format dates for display
        $startDateFormatted = $startDate ? Carbon::parse($startDate)->format('M d, Y') : '';
        $endDateFormatted = $endDate ? Carbon::parse($endDate)->format('M d, Y') : '';
        
        // Get support phone from config
        $supportPhone = config('whatsapp.support_phone', '+19406182531');

        // Normalize language
        $language = strtolower(trim($language));
        if (!in_array($language, ['ar', 'en', 'fr'])) {
            $language = 'ar';
        }

        // Generate payment links for unpaid bills
        $paymentLinks = [];
        foreach ($bills as $bill) {
            // Get bill status and ID (handle both collection items and arrays)
            $billStatus = is_object($bill) ? $bill->status : ($bill['status'] ?? null);
            
            if (in_array($billStatus, ['pending', 'sent'])) {
                $billId = is_object($bill) ? $bill->id : ($bill['id'] ?? null);
                
                if (!$billId) {
                    continue;
                }
                
                // Get or generate payment token
                $billToken = null;
                if (is_object($bill)) {
                    $billToken = $bill->payment_token;
                } else {
                    $billToken = $bill['payment_token'] ?? null;
                }
                
                // Generate payment token if not exists
                if (!$billToken) {
                    $this->generatePaymentToken($billId);
                    // Reload bill to get the new token
                    $billObj = Bill::find($billId);
                    if ($billObj) {
                        $billToken = $billObj->payment_token;
                        // Update the original bill object if it's an object
                        if (is_object($bill)) {
                            $bill->payment_token = $billToken;
                        }
                    }
                }
                
                // Generate payment link - extract just the 5-character suffix
                if ($billToken) {
                    $tokenSuffix = str_replace('elmcorner', '', $billToken);
                    $paymentUrl = url("/payment/{$tokenSuffix}");
                    $paymentLinks[] = $paymentUrl;
                }
            }
        }

        // Format message based on language
        if ($language === 'en') {
            $message = "🎓 *ELM CORNER ACADEMY*\n\n";
            $message .= "👋 Hello {$studentName},\n\n";
            $message .= "📋 *That's your bill*\n";
            $message .= "📅 From: {$startDateFormatted}\n";
            $message .= "📅 To: {$endDateFormatted}\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n";
            $message .= "📊 *Bill Details*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "⏱️ Total Hours: *{$totalHours} hours*\n";
            $message .= "💰 Total Amount: *{$totalAmount} {$currency}*\n\n";
            
            if (count($paymentLinks) > 0) {
                $message .= "━━━━━━━━━━━━━━━━━━\n";
                $message .= "💳 *Payment Link*\n";
                $message .= "━━━━━━━━━━━━━━━━━━\n\n";
                foreach ($paymentLinks as $index => $link) {
                    $message .= ($index + 1) . ". {$link}\n";
                }
                $message .= "\n";
            }
            
            $message .= "━━━━━━━━━━━━━━━━━━\n";
            $message .= "🆘 *Support*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📱 WhatsApp: {$supportPhone}\n";
            $message .= "💬 Need help? Contact us anytime!\n\n";
            $message .= "Thank you! 🙏";
        } elseif ($language === 'fr') {
            $message = "🎓 *ELM CORNER ACADEMY*\n\n";
            $message .= "👋 Bonjour {$studentName},\n\n";
            $message .= "📋 *Voici votre facture*\n";
            $message .= "📅 Du: {$startDateFormatted}\n";
            $message .= "📅 Au: {$endDateFormatted}\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n";
            $message .= "📊 *Détails de la facture*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "⏱️ Heures totales: *{$totalHours} heures*\n";
            $message .= "💰 Montant total: *{$totalAmount} {$currency}*\n\n";
            
            if (count($paymentLinks) > 0) {
                $message .= "━━━━━━━━━━━━━━━━━━\n";
                $message .= "💳 *Lien de paiement*\n";
                $message .= "━━━━━━━━━━━━━━━━━━\n\n";
                foreach ($paymentLinks as $index => $link) {
                    $message .= ($index + 1) . ". {$link}\n";
                }
                $message .= "\n";
            }
            
            $message .= "━━━━━━━━━━━━━━━━━━\n";
            $message .= "🆘 *Support*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📱 WhatsApp: {$supportPhone}\n";
            $message .= "💬 Besoin d'aide? Contactez-nous à tout moment!\n\n";
            $message .= "Merci! 🙏";
        } else {
            // Arabic (default)
            // Format dates in Arabic-friendly format
            $startDateAr = $startDate ? Carbon::parse($startDate)->format('Y-m-d') : '';
            $endDateAr = $endDate ? Carbon::parse($endDate)->format('Y-m-d') : '';
            
            $message = "🎓 *أكاديمية إلم كورنر*\n\n";
            $message .= "👋 مرحباً {$studentName},\n\n";
            $message .= "📋 *هذه فاتورتك*\n";
            $message .= "📅 من: {$startDateAr}\n";
            $message .= "📅 إلى: {$endDateAr}\n\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n";
            $message .= "📊 *تفاصيل الفاتورة*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "⏱️ إجمالي الساعات: *{$totalHours} ساعة*\n";
            $message .= "💰 المبلغ الإجمالي: *{$totalAmount} {$currency}*\n\n";
            
            if (count($paymentLinks) > 0) {
                $message .= "━━━━━━━━━━━━━━━━━━\n";
                $message .= "💳 *رابط الدفع*\n";
                $message .= "━━━━━━━━━━━━━━━━━━\n\n";
                foreach ($paymentLinks as $index => $link) {
                    $message .= ($index + 1) . ". {$link}\n";
                }
                $message .= "\n";
            }
            
            $message .= "━━━━━━━━━━━━━━━━━━\n";
            $message .= "🆘 *الدعم الفني*\n";
            $message .= "━━━━━━━━━━━━━━━━━━\n\n";
            $message .= "📱 واتساب: {$supportPhone}\n";
            $message .= "💬 تحتاج مساعدة؟ تواصل معنا في أي وقت!\n\n";
            $message .= "شكراً لك! 🙏";
        }

        return $message;
    }

    /**
     * Generate PDF for bill
     */
    public function generateBillPDF(int $billId): string
    {
        $bill = Bill::with([
            'student.family',
            'teacher.user',
            'package',
        ])->findOrFail($billId);

        // Get classes included in this bill
        $classes = [];
        if ($bill->class_ids && is_array($bill->class_ids)) {
            $classes = ClassInstance::with(['teacher.user', 'course'])
                ->whereIn('id', $bill->class_ids)
                ->orderBy('class_date', 'asc')
                ->orderBy('start_time', 'asc')
                ->get();
        } elseif ($bill->class_id) {
            $class = ClassInstance::with(['teacher.user', 'course'])->find($bill->class_id);
            if ($class) {
                $classes = collect([$class]);
            }
        }

        // Store PDF
        $filename = 'bill_' . $billId . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        $path = 'bills/' . $filename;
        $fullPath = storage_path('app/' . $path);

        // Ensure directory exists
        if (!file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        // Generate PDF using Spatie PDF
        try {
            \Spatie\LaravelPdf\Facades\Pdf::view('bills.pdf', [
                'bill' => $bill,
                'classes' => $classes,
            ])
                ->format(\Spatie\LaravelPdf\Enums\Format::A4)
                ->orientation(\Spatie\LaravelPdf\Enums\Orientation::Portrait)
                ->save($fullPath);

            // Verify file was created
            if (!file_exists($fullPath)) {
                throw new \Exception('PDF file was not created at path: ' . $fullPath);
            }

            return $path;
        } catch (\Exception $e) {
            \Log::error('PDF generation failed for bill ID: ' . $billId);
            \Log::error('Error: ' . $e->getMessage());
            \Log::error('Stack trace: ' . $e->getTraceAsString());
            throw new \Exception('Failed to generate PDF: ' . $e->getMessage());
        }
    }

    /**
     * Get all bills with filters
     */
    public function getBills(array $filters = []): array
    {
        $query = Bill::with(['student', 'teacher.user', 'package']);

        if (isset($filters['year']) && isset($filters['month'])) {
            $query->whereYear('bill_date', (int)$filters['year'])
                ->whereMonth('bill_date', (int)$filters['month']);
        }

        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('status', $filters['status']);
            } else {
                $query->where('status', $filters['status']);
            }
        }

        if (isset($filters['student_id'])) {
            $query->where('student_id', $filters['student_id']);
        }

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['is_custom'])) {
            // Convert string "true"/"false" to boolean if needed
            $isCustom = $filters['is_custom'];
            if (is_string($isCustom)) {
                $isCustom = filter_var($isCustom, FILTER_VALIDATE_BOOLEAN);
            }
            $query->where('is_custom', (bool)$isCustom);
        }

        $bills = $query->orderBy('bill_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        // Ensure student relationship is loaded
        $bills->loadMissing('student');

        // Group by year/month
        $grouped = $bills->groupBy(function ($bill) {
            return Carbon::parse($bill->bill_date)->format('Y-m');
        });

        $result = [];
        foreach ($grouped as $yearMonth => $monthBills) {
            [$year, $month] = explode('-', $yearMonth);
            $result[$yearMonth] = [
                'year' => (int)$year,
                'month' => (int)$month,
                'bills' => $monthBills->values(),
                'paid' => $monthBills->where('status', 'paid')->values(),
                'unpaid' => $monthBills->whereIn('status', ['pending', 'sent'])->values(),
            ];
        }

        return $result;
    }
}
