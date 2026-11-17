<?php

namespace App\Support;

/**
 * National public holidays from the joint decrees of the Ministers of Religious
 * Affairs, Manpower, and Administrative Reform.
 *
 * @see https://setneg.go.id/baca/index/inilah_skb_3_menteri_libur_nasional_dan_cuti_bersama_2026
 * @see https://setneg.go.id/baca/index/inilah_skb_3_menteri_libur_nasional_dan_cuti_bersama_2027
 */
class IndonesianPublicHolidays
{
    /**
     * @return array<string, string> date (Y-m-d) => holiday name
     */
    public static function dates(): array
    {
        return [
            '2026-01-01' => "New Year's Day",
            '2026-01-16' => "Isra and Mi'raj",
            '2026-02-17' => 'Lunar New Year',
            '2026-03-19' => 'Nyepi',
            '2026-03-21' => 'Eid al-Fitr',
            '2026-03-22' => 'Eid al-Fitr',
            '2026-04-03' => 'Good Friday',
            '2026-04-05' => 'Easter',
            '2026-05-01' => 'Labour Day',
            '2026-05-14' => 'Ascension Day',
            '2026-05-27' => 'Eid al-Adha',
            '2026-05-31' => 'Vesak',
            '2026-06-01' => 'Pancasila Day',
            '2026-06-16' => 'Islamic New Year',
            '2026-08-17' => 'Independence Day',
            '2026-08-25' => "Prophet Muhammad's Birthday",
            '2026-12-25' => 'Christmas',
            '2027-01-01' => "New Year's Day",
            '2027-01-05' => "Isra and Mi'raj",
            '2027-02-06' => 'Lunar New Year',
            '2027-03-08' => 'Nyepi',
            '2027-03-10' => 'Eid al-Fitr',
            '2027-03-11' => 'Eid al-Fitr',
            '2027-03-26' => 'Good Friday',
            '2027-03-28' => 'Easter',
            '2027-05-01' => 'Labour Day',
            '2027-05-06' => 'Ascension Day',
            '2027-05-17' => 'Eid al-Adha',
            '2027-05-20' => 'Vesak',
            '2027-06-01' => 'Pancasila Day',
            '2027-06-06' => 'Islamic New Year',
            '2027-08-15' => "Prophet Muhammad's Birthday",
            '2027-08-17' => 'Independence Day',
            '2027-12-25' => 'Christmas',
            '2027-12-26' => "Isra and Mi'raj",
        ];
    }
}
