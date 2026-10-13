<?php

namespace Database\Seeders;

use App\Enums\LandingPageGroup;
use App\Models\LoanProduct;

/**
 * Seeds the "By Location" landing pages (Personal Loan in Delhi, ...) — one
 * per metro/prime city for every loan product that already has By Amount /
 * By Type / By Need pages. Reuses LoanLandingPageSeeder's copy helpers, so
 * the body is the same real product range/eligibility/documents text plus
 * one city sentence naming well-known areas — no invented numbers.
 *
 * Kept separate from LoanLandingPageSeeder so it can be run on its own
 * against an existing database (deploys only migrate, never seed):
 * `php artisan db:seed --class=LoanLocationPageSeeder --force` adds the city
 * pages without resetting admin edits to the existing sub-pages.
 */
class LoanLocationPageSeeder extends LoanLandingPageSeeder
{
    /**
     * Metros first, then prime cities — this is also the menu order.
     *
     * @var array<string, string> City => well-known areas within it.
     */
    private const CITIES = [
        'Delhi' => 'Connaught Place, Dwarka, Rohini, Saket, Janakpuri and Laxmi Nagar',
        'Mumbai' => 'Andheri, Bandra, Powai, Borivali, Lower Parel and Chembur',
        'Bengaluru' => 'Whitefield, Koramangala, Indiranagar, Electronic City, HSR Layout and Jayanagar',
        'Hyderabad' => 'HITEC City, Gachibowli, Madhapur, Banjara Hills, Kukatpally and Secunderabad',
        'Chennai' => 'T. Nagar, Anna Nagar, Adyar, Velachery, OMR and Tambaram',
        'Kolkata' => 'Salt Lake, New Town, Park Street, Ballygunge, Behala and Dum Dum',
        'Pune' => 'Hinjewadi, Kothrud, Viman Nagar, Hadapsar, Baner and Pimpri-Chinchwad',
        'Ahmedabad' => 'SG Highway, Navrangpura, Satellite, Maninagar, Bopal and Chandkheda',
        'Noida' => 'Sector 18, Sector 62, Sector 63, Sector 137 and Noida Extension',
        'Gurugram' => 'Cyber City, Golf Course Road, Sohna Road, MG Road and Udyog Vihar',
        'Jaipur' => 'Malviya Nagar, Vaishali Nagar, Mansarovar, C-Scheme and Jagatpura',
        'Lucknow' => 'Gomti Nagar, Hazratganj, Aliganj, Indira Nagar and Alambagh',
        'Chandigarh' => 'Sector 17, Sector 35, Manimajra and the wider Tricity of Mohali and Panchkula',
        'Indore' => 'Vijay Nagar, Palasia, Rajendra Nagar, Bhawarkua and Nipania',
        'Kochi' => 'Kakkanad, Edappally, Kaloor, Vyttila and Fort Kochi',
        'Surat' => 'Adajan, Vesu, Athwa, Varachha and Katargam',
        'Nagpur' => 'Dharampeth, Sitabuldi, Civil Lines, Manish Nagar and Sadar',
        'Bhopal' => 'MP Nagar, Arera Colony, Kolar Road, Hoshangabad Road and New Market',
        'Coimbatore' => 'RS Puram, Gandhipuram, Peelamedu, Saibaba Colony and Singanallur',
        'Visakhapatnam' => 'MVP Colony, Dwaraka Nagar, Gajuwaka, Madhurawada and Seethammadhara',
        'Vadodara' => 'Alkapuri, Akota, Gotri, Manjalpur and Fatehgunj',
        'Patna' => 'Boring Road, Kankarbagh, Patliputra Colony, Bailey Road and Rajendra Nagar',
        'Bhubaneswar' => 'Saheed Nagar, Patia, Chandrasekharpur, Nayapalli and Khandagiri',
        'Dehradun' => 'Rajpur Road, Clement Town, Prem Nagar, Ballupur and Sahastradhara Road',
    ];

    public function run(): void
    {
        LoanProduct::query()
            ->whereHas('landingPages', fn ($query) => $query->where('group', '!=', LandingPageGroup::ByLocation))
            ->get()
            ->each(fn (LoanProduct $product) => $this->seedByLocation($product));
    }

    private function seedByLocation(LoanProduct $product): void
    {
        $sortOrder = 0;

        foreach (self::CITIES as $city => $areas) {
            $paragraphs = [
                "Looking for a {$product->name} in {$city}? FynnEdge compares {$product->name} offers from partner banks and NBFCs for applicants living or working across {$city} — including {$areas} — so you can check eligibility and start your application online.",
                $this->rangeSentence($product),
                $this->eligibilitySentence($product),
                $this->documentsSentence($product),
                self::DISCLAIMER,
            ];

            $this->upsertLandingPage(
                product: $product,
                group: LandingPageGroup::ByLocation,
                title: "{$product->name} in {$city}",
                amount: null,
                excerpt: "Compare {$product->name} offers from FynnEdge partner banks and NBFCs if you live or work in {$city}.",
                paragraphs: $paragraphs,
                sortOrder: $sortOrder++,
                city: $city,
            );
        }
    }
}
