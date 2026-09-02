<?php

namespace App\Support\Options;

class CityOptions
{
    /**
     * A bundled offline list so city fields work without any external API — spans major
     * Indian metros, state capitals and large towns. Every consuming UI must still offer
     * an "Other" fallback so an unlisted city never blocks the user; this is a convenience
     * list, not the source of truth.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function all(): array
    {
        return collect([
            // Metros
            'Mumbai', 'Delhi', 'Bengaluru', 'Hyderabad', 'Ahmedabad', 'Chennai', 'Kolkata', 'Pune',
            // State capitals & major cities
            'Jaipur', 'Lucknow', 'Kanpur', 'Nagpur', 'Indore', 'Bhopal', 'Visakhapatnam',
            'Patna', 'Vadodara', 'Ludhiana', 'Agra', 'Nashik', 'Faridabad', 'Meerut',
            'Rajkot', 'Kalyan-Dombivli', 'Vasai-Virar', 'Varanasi', 'Srinagar', 'Aurangabad',
            'Dhanbad', 'Amritsar', 'Navi Mumbai', 'Allahabad (Prayagraj)', 'Ranchi',
            'Howrah', 'Coimbatore', 'Jabalpur', 'Gwalior', 'Vijayawada', 'Jodhpur',
            'Madurai', 'Raipur', 'Kota', 'Guwahati', 'Chandigarh', 'Thiruvananthapuram',
            'Solapur', 'Hubballi-Dharwad', 'Bareilly', 'Moradabad', 'Mysuru', 'Gurugram',
            'Aligarh', 'Jalandhar', 'Bhubaneswar', 'Salem', 'Warangal', 'Guntur',
            'Bhiwandi', 'Saharanpur', 'Gorakhpur', 'Bikaner', 'Amravati', 'Noida',
            'Jamshedpur', 'Bhilai', 'Cuttack', 'Firozabad', 'Kochi', 'Nellore',
            'Bhavnagar', 'Dehradun', 'Durgapur', 'Asansol', 'Rourkela', 'Nanded',
            'Kolhapur', 'Ajmer', 'Akola', 'Gulbarga', 'Jamnagar', 'Ujjain', 'Loni',
            'Siliguri', 'Jhansi', 'Ulhasnagar', 'Jammu', 'Sangli-Miraj & Kupwad',
            'Mangaluru', 'Erode', 'Belgaum', 'Ambattur', 'Tirunelveli', 'Malegaon',
            'Gaya', 'Jalgaon', 'Udaipur', 'Maheshtala', 'Tiruchirappalli', 'Davanagere',
            'Kozhikode', 'Akbarpur', 'Kurnool', 'Rajpur Sonarpur', 'Bokaro', 'South Dumdum',
            'Bellary', 'Patiala', 'Gopalpur', 'Agartala', 'Bhagalpur', 'Muzaffarnagar',
            'Bhatpara', 'Panihati', 'Latur', 'Dhule', 'Rohtak', 'Korba', 'Bhilwara',
            'Berhampur', 'Muzaffarpur', 'Ahmednagar', 'Mathura', 'Kollam', 'Avadi',
            'Kadapa', 'Kamarhati', 'Sambalpur', 'Bilaspur', 'Shahjahanpur', 'Satara',
            'Bijapur', 'Rampur', 'Shivamogga', 'Chandrapur', 'Junagadh', 'Thrissur',
            'Alwar', 'Bardhaman', 'Kulti', 'Kakinada', 'Nizamabad', 'Parbhani',
            'Tumkur', 'Khammam', 'Ozhukarai', 'Bihar Sharif', 'Panipat', 'Darbhanga',
            'Bally', 'Aizawl', 'Dewas', 'Ichalkaranji', 'Karnal', 'Bathinda', 'Jalna',
            'Eluru', 'Kirari Suleman Nagar', 'Barabanki', 'Purnia', 'Satna', 'Mau',
            'Sonipat', 'Farrukhabad', 'Sagar', 'Rourkela', 'Durg', 'Imphal', 'Ratlam',
            'Hapur', 'Arrah', 'Anantapur', 'Karimnagar', 'Etawah', 'Ambernath', 'North Dumdum',
            'Bharatpur', 'Begusarai', 'New Delhi', 'Gandhinagar', 'Baranagar', 'Tirupati',
            'Puducherry', 'Sikar', 'Thoothukudi', 'Rewa', 'Mirzapur', 'Raichur', 'Pali',
            'Ramagundam', 'Silchar', 'Haridwar', 'Vijayanagaram', 'Katihar', 'Nagercoil',
            'Sri Ganganagar', 'Karawal Nagar', 'Mango', 'Thanjavur', 'Bulandshahr',
            'Uluberia', 'Katni', 'Sambhal', 'Singrauli', 'Nadiad', 'Secunderabad',
            'Naihati', 'Yamunanagar', 'Bidhannagar', 'Pallavaram', 'Bidar', 'Munger',
            'Panchkula', 'Burhanpur', 'Raurkela Industrial Township', 'Kharagpur',
            'Dindigul', 'Gandhidham', 'Hospet', 'Nangloi Jat', 'Malda', 'Ongole',
            'Deoghar', 'Chapra', 'Haldia', 'Khandwa', 'Nandyal', 'Morena', 'Amroha',
            'Anand', 'Bhind', 'Bhalswa Jahangir Pur', 'Madhyamgram', 'Bhiwani', 'Berhampore',
            'Ambala', 'Morbi', 'Fatehpur', 'Rae Bareli', 'Khora', 'Chittoor', 'Bhusawal',
        ])
            ->unique()
            ->map(fn (string $name) => ['value' => $name, 'label' => $name])
            ->values()
            ->all();
    }
}
