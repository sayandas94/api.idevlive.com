<?php

namespace App\Http\Controllers;

abstract class Controller
{
    protected function get_cr_country_name($region)
    {
        $country = match($region) {
            'us' => 'United States',
            'in' => 'India',
            'default' => 'United States'
        };

        return $country;
    }

    protected function connect_reseller_country_code_from_full_name($country)
    {
        // $country = match($country) {
        //     'United States of America' => 'United States',
        //     'India' => 'IN',
        //     'Canada' => 'CA'
        // };
    }

    protected function two_letter_country_code_from_full_name($country)
    {
        $country_code = match($country) {
            'United States' => 'US',
            'India' => 'IN',
            'Canada' => 'CA'
        };

        return $country_code;
    }

    protected function phone_number_cc($country)
    {
        $cc = match($country) {
            'United States' => '1',
            'India' => '91'
        };

        return $cc;
    }

    protected function currency_symbol($region)
    {
        $symbol = match ($region) {
            'in' => '₹',
            'us' => '$',
        };

        return $symbol;
    }
}
