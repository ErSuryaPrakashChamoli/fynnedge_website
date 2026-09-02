<?php

namespace App\Support\Options;

class EmployerOptions
{
    /**
     * A bundled offline list so employer-name fields work without any external API — spans
     * major Indian employers across sectors. Every consuming UI must still offer an "Other"
     * fallback so an unlisted employer never blocks the user; this is a convenience list,
     * not the source of truth.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function all(): array
    {
        return collect([
            // IT / Technology
            'Tata Consultancy Services (TCS)', 'Infosys', 'Wipro', 'HCLTech', 'Tech Mahindra',
            'Cognizant', 'Accenture', 'IBM India', 'Capgemini', 'LTIMindtree', 'Mphasis',
            'Persistent Systems', 'Zoho', 'Freshworks', 'Microsoft India', 'Google India',
            'Amazon India', 'Adobe India', 'Oracle India', 'SAP India', 'Dell Technologies India',
            'HP India', 'Intel India', 'Qualcomm India', 'Cisco India',
            // Banking / Finance / Insurance
            'HDFC Bank', 'ICICI Bank', 'State Bank of India (SBI)', 'Axis Bank',
            'Kotak Mahindra Bank', 'Yes Bank', 'IndusInd Bank', 'Bank of Baroda',
            'Punjab National Bank', 'Canara Bank', 'IDFC First Bank', 'Bajaj Finserv',
            'Bajaj Finance', 'HDFC Life', 'ICICI Prudential', 'Life Insurance Corporation (LIC)',
            'SBI Life Insurance', 'Muthoot Finance', 'Cholamandalam Investment and Finance',
            'Shriram Finance',
            // FMCG
            'Hindustan Unilever', 'ITC Limited', 'Nestle India', 'Britannia Industries',
            'Dabur India', 'Marico', 'Godrej Consumer Products', 'Colgate-Palmolive India',
            'Procter & Gamble India', 'Parle Products', 'Patanjali Ayurved',
            // Manufacturing / Automotive / Energy
            'Tata Motors', 'Mahindra & Mahindra', 'Maruti Suzuki India', 'Bajaj Auto',
            'Hero MotoCorp', 'TVS Motor Company', 'Ashok Leyland', 'Eicher Motors', 'Tata Steel',
            'JSW Steel', 'Hindalco Industries', 'Larsen & Toubro (L&T)', 'Bharat Forge',
            'Tata Power', 'Adani Power', 'NTPC',
            // Pharma / Healthcare
            'Sun Pharmaceutical', "Dr. Reddy's Laboratories", 'Cipla', 'Lupin',
            'Aurobindo Pharma', "Divi's Laboratories", 'Biocon', 'Zydus Lifesciences',
            'Torrent Pharmaceuticals', 'Apollo Hospitals', 'Fortis Healthcare',
            // Retail / E-commerce
            'Reliance Retail', 'Flipkart', 'Amazon', 'Myntra', 'Nykaa', 'BigBasket',
            'Avenue Supermarts (DMart)', 'Tata CLiQ', 'Trent Limited', 'Titan Company',
            // Telecom
            'Reliance Jio', 'Bharti Airtel', 'Vodafone Idea', 'BSNL',
            // PSU
            'Oil and Natural Gas Corporation (ONGC)', 'Indian Oil Corporation (IOCL)',
            'Bharat Petroleum (BPCL)', 'Hindustan Petroleum (HPCL)', 'Coal India',
            'Steel Authority of India (SAIL)', 'Bharat Heavy Electricals (BHEL)',
            'Power Grid Corporation of India', 'GAIL India',
            // Conglomerates
            'Reliance Industries', 'Tata Sons', 'Aditya Birla Group', 'Adani Group',
            'Mahindra Group', 'Godrej Group',
            // Startups / New-age
            'Zomato', 'Swiggy', 'Paytm (One97 Communications)', 'PhonePe', 'Ola Cabs',
            "Byju's", 'Unacademy', 'CRED', 'Razorpay', 'Zerodha', 'Meesho', 'Delhivery',
            'Urban Company', 'PolicyBazaar', 'Lenskart', 'Imagine Marketing (boAt)',
            'OYO Rooms', 'Dream11', 'upGrad',
            // Aviation
            'IndiGo (InterGlobe Aviation)', 'Air India', 'SpiceJet', 'Vistara',
            // Media & Entertainment
            'Zee Entertainment', 'Sony Pictures Networks India', 'Star India',
            'Disney+ Hotstar',
            // Consulting / Professional Services
            'Deloitte India', 'EY (Ernst & Young) India', 'KPMG India', 'PwC India',
            'McKinsey & Company India', 'Boston Consulting Group India',
            // Real Estate
            'DLF Limited', 'Godrej Properties', 'Prestige Group', 'Oberoi Realty',
            'Sobha Limited',
            // Other well-known employers
            'Asian Paints', 'Pidilite Industries', 'Havells India', 'Voltas', 'Blue Star',
            'UltraTech Cement', 'Ambuja Cements', 'Grasim Industries',
        ])
            ->map(fn (string $name) => ['value' => $name, 'label' => $name])
            ->all();
    }
}
