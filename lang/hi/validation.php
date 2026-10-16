<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines (Hindi)
    |--------------------------------------------------------------------------
    |
    | Laravel's default validation messages, for visitors who chose Hindi with
    | the header's language switch. Messages a form defines for itself win
    | over these. Constructions are kept gender-neutral (":attribute का मान",
    | ":attribute में ... होने चाहिए") because :attribute can be any field.
    |
    */

    'accepted' => ':attribute को स्वीकार करना ज़रूरी है।',
    'accepted_if' => 'जब :other :value हो, तब :attribute को स्वीकार करना ज़रूरी है।',
    'active_url' => ':attribute एक मान्य URL होना चाहिए।',
    'after' => ':attribute, :date के बाद की तारीख होनी चाहिए।',
    'after_or_equal' => ':attribute, :date या उसके बाद की तारीख होनी चाहिए।',
    'alpha' => ':attribute में केवल अक्षर हो सकते हैं।',
    'alpha_dash' => ':attribute में केवल अक्षर, अंक, डैश (-) और अंडरस्कोर (_) हो सकते हैं।',
    'alpha_num' => ':attribute में केवल अक्षर और अंक हो सकते हैं।',
    'any_of' => ':attribute मान्य नहीं है।',
    'array' => ':attribute एक सूची (array) होनी चाहिए।',
    'array_keys' => ':attribute में केवल ये कुंजियां (keys) हो सकती हैं: :values।',
    'ascii' => ':attribute में केवल सिंगल-बाइट अक्षर, अंक और चिह्न हो सकते हैं।',
    'base64' => ':attribute एक मान्य Base64 स्ट्रिंग होनी चाहिए।',
    'before' => ':attribute, :date से पहले की तारीख होनी चाहिए।',
    'before_or_equal' => ':attribute, :date या उससे पहले की तारीख होनी चाहिए।',
    'between' => [
        'array' => ':attribute में :min से :max आइटम होने चाहिए।',
        'file' => ':attribute का आकार :min से :max किलोबाइट के बीच होना चाहिए।',
        'numeric' => ':attribute का मान :min और :max के बीच होना चाहिए।',
        'string' => ':attribute में :min से :max अक्षर होने चाहिए।',
    ],
    'boolean' => ':attribute का मान हां या नहीं (true या false) होना चाहिए।',
    'can' => ':attribute में ऐसा मान है जिसकी अनुमति नहीं है।',
    'confirmed' => ':attribute की पुष्टि मेल नहीं खाती।',
    'contains' => ':attribute में एक ज़रूरी मान नहीं है।',
    'current_password' => 'पासवर्ड गलत है।',
    'date' => ':attribute एक मान्य तारीख होनी चाहिए।',
    'date_equals' => ':attribute, :date वाली तारीख होनी चाहिए।',
    'date_format' => ':attribute का फ़ॉर्मेट :format होना चाहिए।',
    'decimal' => ':attribute में दशमलव के बाद :decimal अंक होने चाहिए।',
    'declined' => ':attribute को अस्वीकार करना ज़रूरी है।',
    'declined_if' => 'जब :other :value हो, तब :attribute को अस्वीकार करना ज़रूरी है।',
    'different' => ':attribute और :other अलग-अलग होने चाहिए।',
    'digits' => ':attribute में ठीक :digits अंक होने चाहिए।',
    'digits_between' => ':attribute में :min से :max अंक होने चाहिए।',
    'dimensions' => ':attribute की इमेज का आकार (dimensions) सही नहीं है।',
    'distinct' => ':attribute में एक ही मान दो बार है।',
    'doesnt_contain' => ':attribute में इनमें से कोई भी नहीं होना चाहिए: :values।',
    'doesnt_end_with' => ':attribute का अंत इनमें से किसी से नहीं होना चाहिए: :values।',
    'doesnt_start_with' => ':attribute की शुरुआत इनमें से किसी से नहीं होनी चाहिए: :values।',
    'email' => ':attribute एक मान्य ईमेल पता होना चाहिए।',
    'encoding' => ':attribute की एन्कोडिंग :encoding होनी चाहिए।',
    'ends_with' => ':attribute का अंत इनमें से किसी एक से होना चाहिए: :values।',
    'enum' => ':attribute के लिए चुना गया विकल्प मान्य नहीं है।',
    'exists' => ':attribute के लिए चुना गया विकल्प मान्य नहीं है।',
    'extensions' => ':attribute का एक्सटेंशन इनमें से कोई एक होना चाहिए: :values।',
    'file' => ':attribute एक फ़ाइल होनी चाहिए।',
    'filled' => ':attribute को खाली नहीं छोड़ा जा सकता।',
    'gt' => [
        'array' => ':attribute में :value से ज़्यादा आइटम होने चाहिए।',
        'file' => ':attribute का आकार :value किलोबाइट से ज़्यादा होना चाहिए।',
        'numeric' => ':attribute का मान :value से ज़्यादा होना चाहिए।',
        'string' => ':attribute में :value से ज़्यादा अक्षर होने चाहिए।',
    ],
    'gte' => [
        'array' => ':attribute में कम से कम :value आइटम होने चाहिए।',
        'file' => ':attribute का आकार कम से कम :value किलोबाइट होना चाहिए।',
        'numeric' => ':attribute का मान कम से कम :value होना चाहिए।',
        'string' => ':attribute में कम से कम :value अक्षर होने चाहिए।',
    ],
    'hex_color' => ':attribute एक मान्य हेक्साडेसिमल रंग (hex color) होना चाहिए।',
    'image' => ':attribute एक इमेज होनी चाहिए।',
    'in' => ':attribute के लिए चुना गया विकल्प मान्य नहीं है।',
    'in_array' => ':attribute का :other में होना ज़रूरी है।',
    'in_array_keys' => ':attribute में इनमें से कम से कम एक कुंजी (key) होनी चाहिए: :values।',
    'integer' => ':attribute एक पूर्ण संख्या होनी चाहिए।',
    'ip' => ':attribute एक मान्य IP पता होना चाहिए।',
    'ipv4' => ':attribute एक मान्य IPv4 पता होना चाहिए।',
    'ipv6' => ':attribute एक मान्य IPv6 पता होना चाहिए।',
    'json' => ':attribute एक मान्य JSON स्ट्रिंग होनी चाहिए।',
    'list' => ':attribute एक क्रमवार सूची (list) होनी चाहिए।',
    'lowercase' => ':attribute केवल छोटे अक्षरों (lowercase) में लिखें।',
    'lt' => [
        'array' => ':attribute में :value से कम आइटम होने चाहिए।',
        'file' => ':attribute का आकार :value किलोबाइट से कम होना चाहिए।',
        'numeric' => ':attribute का मान :value से कम होना चाहिए।',
        'string' => ':attribute में :value से कम अक्षर होने चाहिए।',
    ],
    'lte' => [
        'array' => ':attribute में :value से ज़्यादा आइटम नहीं होने चाहिए।',
        'file' => ':attribute का आकार :value किलोबाइट से ज़्यादा नहीं होना चाहिए।',
        'numeric' => ':attribute का मान :value से ज़्यादा नहीं होना चाहिए।',
        'string' => ':attribute में :value से ज़्यादा अक्षर नहीं होने चाहिए।',
    ],
    'mac_address' => ':attribute एक मान्य MAC पता होना चाहिए।',
    'max' => [
        'array' => ':attribute में :max से ज़्यादा आइटम नहीं हो सकते।',
        'file' => ':attribute का आकार :max किलोबाइट से ज़्यादा नहीं हो सकता।',
        'numeric' => ':attribute का मान :max से ज़्यादा नहीं हो सकता।',
        'string' => ':attribute में :max से ज़्यादा अक्षर नहीं हो सकते।',
    ],
    'max_digits' => ':attribute में :max से ज़्यादा अंक नहीं हो सकते।',
    'mimes' => ':attribute इनमें से किसी प्रकार की फ़ाइल होनी चाहिए: :values।',
    'mimetypes' => ':attribute इनमें से किसी प्रकार की फ़ाइल होनी चाहिए: :values।',
    'min' => [
        'array' => ':attribute में कम से कम :min आइटम होने चाहिए।',
        'file' => ':attribute का आकार कम से कम :min किलोबाइट होना चाहिए।',
        'numeric' => ':attribute का मान कम से कम :min होना चाहिए।',
        'string' => ':attribute में कम से कम :min अक्षर होने चाहिए।',
    ],
    'min_digits' => ':attribute में कम से कम :min अंक होने चाहिए।',
    'missing' => ':attribute नहीं भेजा जाना चाहिए।',
    'missing_if' => 'जब :other :value हो, तब :attribute नहीं भेजा जाना चाहिए।',
    'missing_unless' => ':attribute तभी भेजा जा सकता है जब :other :value हो।',
    'missing_with' => 'जब :values मौजूद हो, तब :attribute नहीं भेजा जाना चाहिए।',
    'missing_with_all' => 'जब :values मौजूद हों, तब :attribute नहीं भेजा जाना चाहिए।',
    'multiple_of' => ':attribute का मान :value का गुणज होना चाहिए।',
    'not_in' => ':attribute के लिए चुना गया विकल्प मान्य नहीं है।',
    'not_regex' => ':attribute का फ़ॉर्मेट सही नहीं है।',
    'numeric' => ':attribute एक संख्या होनी चाहिए।',
    'password' => [
        'letters' => ':attribute में कम से कम एक अक्षर होना चाहिए।',
        'mixed' => ':attribute में कम से कम एक बड़ा (uppercase) और एक छोटा (lowercase) अक्षर होना चाहिए।',
        'numbers' => ':attribute में कम से कम एक अंक होना चाहिए।',
        'symbols' => ':attribute में कम से कम एक चिह्न होना चाहिए।',
        'uncompromised' => 'यह :attribute किसी डेटा लीक में सामने आ चुका है। कृपया कोई दूसरा :attribute चुनें।',
    ],
    'present' => ':attribute का मौजूद होना ज़रूरी है।',
    'present_if' => 'जब :other :value हो, तब :attribute का मौजूद होना ज़रूरी है।',
    'present_unless' => ':attribute का मौजूद होना ज़रूरी है, जब तक :other :value न हो।',
    'present_with' => 'जब :values मौजूद हो, तब :attribute का मौजूद होना ज़रूरी है।',
    'present_with_all' => 'जब :values मौजूद हों, तब :attribute का मौजूद होना ज़रूरी है।',
    'prohibited' => ':attribute की अनुमति नहीं है।',
    'prohibited_if' => 'जब :other :value हो, तब :attribute की अनुमति नहीं है।',
    'prohibited_if_accepted' => 'जब :other स्वीकार किया गया हो, तब :attribute की अनुमति नहीं है।',
    'prohibited_if_declined' => 'जब :other अस्वीकार किया गया हो, तब :attribute की अनुमति नहीं है।',
    'prohibited_unless' => ':attribute की अनुमति तभी है जब :other इनमें से हो: :values।',
    'prohibits' => ':attribute के साथ :other नहीं भेजा जा सकता।',
    'regex' => ':attribute का फ़ॉर्मेट सही नहीं है।',
    'required' => ':attribute भरना ज़रूरी है।',
    'required_array_keys' => ':attribute में इनके लिए एंट्री होनी चाहिए: :values।',
    'required_if' => 'जब :other :value हो, तब :attribute भरना ज़रूरी है।',
    'required_if_accepted' => 'जब :other स्वीकार किया गया हो, तब :attribute भरना ज़रूरी है।',
    'required_if_declined' => 'जब :other अस्वीकार किया गया हो, तब :attribute भरना ज़रूरी है।',
    'required_unless' => ':attribute भरना ज़रूरी है, जब तक :other इनमें से न हो: :values।',
    'required_with' => 'जब :values मौजूद हो, तब :attribute भरना ज़रूरी है।',
    'required_with_all' => 'जब :values मौजूद हों, तब :attribute भरना ज़रूरी है।',
    'required_without' => 'जब :values मौजूद न हो, तब :attribute भरना ज़रूरी है।',
    'required_without_all' => 'जब :values में से कोई भी मौजूद न हो, तब :attribute भरना ज़रूरी है।',
    'same' => ':attribute और :other एक जैसे होने चाहिए।',
    'size' => [
        'array' => ':attribute में :size आइटम होने चाहिए।',
        'file' => ':attribute का आकार :size किलोबाइट होना चाहिए।',
        'numeric' => ':attribute का मान :size होना चाहिए।',
        'string' => ':attribute में :size अक्षर होने चाहिए।',
    ],
    'starts_with' => ':attribute की शुरुआत इनमें से किसी एक से होनी चाहिए: :values।',
    'string' => ':attribute टेक्स्ट होना चाहिए।',
    'timezone' => ':attribute एक मान्य टाइमज़ोन होना चाहिए।',
    'unique' => ':attribute पहले से इस्तेमाल में है।',
    'uploaded' => ':attribute को अपलोड नहीं किया जा सका।',
    'uppercase' => ':attribute केवल बड़े अक्षरों (uppercase) में लिखें।',
    'url' => ':attribute एक मान्य URL होना चाहिए।',
    'ulid' => ':attribute एक मान्य ULID होना चाहिए।',
    'uuid' => ':attribute एक मान्य UUID होना चाहिए।',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Forms on this site pass their own messages to validate(), so nothing is
    | needed here. Use "attribute.rule" => "message" to add one.
    |
    */

    'custom' => [],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | Hindi names for the fields of the public forms, swapped in for
    | :attribute. Keys are the submitted field names exactly as validated:
    | snake_case for HTTP forms and the journey's step fields, camelCase for
    | the Livewire components (credit score check, eligibility calculator).
    | An unlisted field falls back to its English name ("full name").
    |
    */

    'attributes' => [
        // Contact, quick enquiry, loan enquiry and newsletter forms
        'name' => 'नाम',
        'email' => 'ईमेल',
        'phone' => 'मोबाइल नंबर',
        'message' => 'संदेश',
        'loan_amount' => 'लोन राशि',
        'loan_product' => 'लोन का प्रकार',
        'source' => 'स्रोत',
        'source_url' => 'स्रोत URL',
        'website' => 'वेबसाइट',
        'categories' => 'श्रेणियां',

        // OTP verification (quick enquiry, journey, contact page, public OTP API)
        'mobile_number' => 'मोबाइल नंबर',
        'country_code' => 'देश कोड',
        'purpose' => 'उद्देश्य',
        'challenge_id' => 'सत्यापन अनुरोध',
        'otp_challenge_id' => 'सत्यापन अनुरोध',
        'otp' => 'ओटीपी',
        'otp_code' => 'ओटीपी',

        // Credit score check (Livewire)
        'mobileNumber' => 'मोबाइल नंबर',
        'otpCode' => 'ओटीपी',
        'fullName' => 'पूरा नाम',
        'firstName' => 'पहला नाम',
        'middleName' => 'मध्य नाम',
        'lastName' => 'अंतिम नाम',
        'dateOfBirth' => 'जन्म तिथि',
        'panNumber' => 'पैन नंबर',
        'consent' => 'सहमति',

        // Loan eligibility calculator (Livewire)
        'age' => 'उम्र',
        'city' => 'शहर',
        'employmentType' => 'रोज़गार का प्रकार',
        'monthlyIncome' => 'मासिक आय',
        'otherMonthlyIncome' => 'अन्य मासिक आय',
        'existingEmiAmount' => 'मौजूदा ईएमआई राशि',
        'loanAmountRequested' => 'ज़रूरी लोन राशि',
        'preferredTenureMonths' => 'पसंदीदा अवधि (महीने)',
        'creditConsent' => 'क्रेडिट जांच की सहमति',

        // Loan application journey step fields
        'full_name' => 'पूरा नाम',
        'date_of_birth' => 'जन्म तिथि',
        'pan_number' => 'पैन नंबर',
        'employment_type' => 'रोज़गार का प्रकार',
        'monthly_income' => 'मासिक आय',
        'other_monthly_income' => 'अन्य मासिक आय',
        'company_name' => 'कंपनी का नाम',
        'designation' => 'पद',
        'employment_vintage_years' => 'मौजूदा नौकरी में साल',
        'business_name' => 'बिज़नेस का नाम',
        'business_type' => 'बिज़नेस का प्रकार',
        'business_vintage_years' => 'बिज़नेस में साल',
        'annual_turnover' => 'सालाना टर्नओवर',
        'industry' => 'उद्योग',
        'is_gst_registered' => 'जीएसटी रजिस्ट्रेशन',
        'gst_number' => 'जीएसटी नंबर',
        'is_profitable' => 'बिज़नेस का मुनाफ़ा',
        'loan_purpose' => 'लोन का उद्देश्य',
        'preferred_tenure_months' => 'पसंदीदा अवधि (महीने)',
        'has_existing_emis' => 'मौजूदा ईएमआई',
        'existing_emi_amount' => 'मौजूदा ईएमआई राशि',
        'has_existing_cards' => 'मौजूदा क्रेडिट कार्ड',
        'existing_cards_count' => 'क्रेडिट कार्ड की संख्या',
        'property_type' => 'प्रॉपर्टी का प्रकार',
        'property_value' => 'प्रॉपर्टी की अनुमानित कीमत',
        'property_status' => 'प्रॉपर्टी की स्थिति',
        'property_ownership' => 'प्रॉपर्टी का मालिकाना हक',
        'existing_loan_on_property' => 'प्रॉपर्टी पर मौजूदा लोन',
        'existing_loan_outstanding' => 'बकाया लोन राशि',
        'own_contribution' => 'आपका अपना योगदान',
        'vehicle_value' => 'वाहन की ऑन-रोड कीमत',
        'vehicle_condition' => 'वाहन की स्थिति',
        'tractor_value' => 'ट्रैक्टर की ऑन-रोड कीमत',
        'tractor_condition' => 'ट्रैक्टर की स्थिति',
        'annual_farm_income' => 'खेती से सालाना आय',
        'land_holding_acres' => 'ज़मीन (एकड़ में)',
        'land_ownership' => 'ज़मीन का मालिकाना हक',
        'gold_item_type' => 'सोने के आइटम का प्रकार',
        'estimated_gold_weight_grams' => 'सोने का अनुमानित वज़न (ग्राम)',
        'mudra_tier' => 'मुद्रा लोन की श्रेणी',
        'credit_check_consent' => 'क्रेडिट जांच की सहमति',
        'platform_consent' => 'प्लेटफ़ॉर्म की शर्तों पर सहमति',

        // Application follow-up (assistance choice, document upload)
        'assistance_preference' => 'सहायता का तरीका',
        'documents' => 'दस्तावेज़',
        'document_labels' => 'दस्तावेज़ का नाम',
    ],

];
