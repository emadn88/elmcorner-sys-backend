<?php

return [
    'provider' => env('WHATSAPP_PROVIDER', 'twilio'), // twilio | meta | wasender

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'from' => env('TWILIO_WHATSAPP_FROM'),
    ],

    'meta' => [
        'token' => env('META_WHATSAPP_TOKEN'),
        'phone_id' => env('META_WHATSAPP_PHONE_ID'),
        'business_account_id' => env('META_WHATSAPP_BUSINESS_ACCOUNT_ID'),
    ],

    'wasender' => [
        'api_key' => env('WASENDER_API_KEY'),
        'base_url' => env('WASENDER_BASE_URL', 'https://wasenderapi.com/api'),
    ],

    'monitoring' => [
        'enabled' => env('WHATSAPP_MONITORING_ENABLED', true),
        'phone' => env('WHATSAPP_MONITORING_PHONE', '+19406182531'),
    ],

    'templates' => [
        'lesson_reminder' => 'تذكير: لديك درس اليوم الساعة {time}',
        'package_finished' => 'انتهت الحصص! للتجديد، اضغط هنا: {link}',
        'bill_sent' => 'فاتورتك جاهزة: {link}',
        'duty_assigned' => 'تم تعيين واجب جديد: {link}',
        'report_ready' => 'تقريرك جاهز: {link}',
        'reactivation_offer' => 'مرحباً {name}! نود أن نراك مرة أخرى. هل ترغب في استئناف دروسك؟ {link}',
    ],
];
