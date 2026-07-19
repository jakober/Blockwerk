<?php
declare(strict_types=1);

namespace Core;

/**
 * Länderliste (deutsch) für die Länder-Auswahl in Versand & Kasse.
 * Deutschland, Österreich, Schweiz stehen immer oben, der Rest alphabetisch.
 */
class Countries
{
    private const TOP = ['Deutschland', 'Österreich', 'Schweiz'];

    /** Übrige Länder (ohne die drei oben), alphabetisch. */
    private const REST = [
        'Ägypten', 'Albanien', 'Algerien', 'Andorra', 'Angola', 'Argentinien', 'Armenien', 'Aserbaidschan',
        'Äthiopien', 'Australien', 'Bahamas', 'Bahrain', 'Bangladesch', 'Barbados', 'Belgien', 'Belize',
        'Benin', 'Bhutan', 'Bolivien', 'Bosnien und Herzegowina', 'Botswana', 'Brasilien', 'Brunei',
        'Bulgarien', 'Burkina Faso', 'Burundi', 'Chile', 'China', 'Costa Rica', 'Dänemark', 'Dominica',
        'Dominikanische Republik', 'Dschibuti', 'Ecuador', 'El Salvador', 'Elfenbeinküste', 'Eritrea',
        'Estland', 'Eswatini', 'Fidschi', 'Finnland', 'Frankreich', 'Gabun', 'Gambia', 'Georgien', 'Ghana',
        'Grenada', 'Griechenland', 'Guatemala', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Honduras',
        'Indien', 'Indonesien', 'Irak', 'Iran', 'Irland', 'Island', 'Israel', 'Italien', 'Jamaika', 'Japan',
        'Jemen', 'Jordanien', 'Kambodscha', 'Kamerun', 'Kanada', 'Kap Verde', 'Kasachstan', 'Katar', 'Kenia',
        'Kirgisistan', 'Kiribati', 'Kolumbien', 'Komoren', 'Kongo (Demokratische Republik)', 'Kongo (Republik)',
        'Kroatien', 'Kuba', 'Kuwait', 'Laos', 'Lesotho', 'Lettland', 'Libanon', 'Liberia', 'Libyen',
        'Liechtenstein', 'Litauen', 'Luxemburg', 'Madagaskar', 'Malawi', 'Malaysia', 'Malediven', 'Mali',
        'Malta', 'Marokko', 'Marshallinseln', 'Mauretanien', 'Mauritius', 'Mexiko', 'Mikronesien', 'Moldau',
        'Monaco', 'Mongolei', 'Montenegro', 'Mosambik', 'Myanmar', 'Namibia', 'Nauru', 'Nepal', 'Neuseeland',
        'Nicaragua', 'Niederlande', 'Niger', 'Nigeria', 'Nordkorea', 'Nordmazedonien', 'Norwegen', 'Oman',
        'Pakistan', 'Palau', 'Panama', 'Papua-Neuguinea', 'Paraguay', 'Peru', 'Philippinen', 'Polen',
        'Portugal', 'Ruanda', 'Rumänien', 'Russland', 'Salomonen', 'Sambia', 'Samoa', 'San Marino',
        'São Tomé und Príncipe', 'Saudi-Arabien', 'Schweden', 'Senegal', 'Serbien', 'Seychellen',
        'Sierra Leone', 'Simbabwe', 'Singapur', 'Slowakei', 'Slowenien', 'Somalia', 'Spanien', 'Sri Lanka',
        'St. Kitts und Nevis', 'St. Lucia', 'St. Vincent und die Grenadinen', 'Südafrika', 'Sudan', 'Südkorea',
        'Südsudan', 'Suriname', 'Syrien', 'Tadschikistan', 'Tansania', 'Thailand', 'Timor-Leste', 'Togo',
        'Tonga', 'Trinidad und Tobago', 'Tschad', 'Tschechien', 'Tunesien', 'Türkei', 'Turkmenistan', 'Tuvalu',
        'Uganda', 'Ukraine', 'Ungarn', 'Uruguay', 'Usbekistan', 'Vanuatu', 'Vatikanstadt', 'Venezuela',
        'Vereinigte Arabische Emirate', 'Vereinigte Staaten', 'Vereinigtes Königreich', 'Vietnam',
        'Weißrussland', 'Zentralafrikanische Republik', 'Zypern',
    ];

    /** Alle Länder: die drei oben, dann alphabetisch. */
    public static function all(): array
    {
        return array_merge(self::TOP, self::REST);
    }

    /**
     * Eine Teilmenge sortieren: Deutschland/Österreich/Schweiz zuerst (in
     * dieser Reihenfolge, falls enthalten), der Rest alphabetisch.
     */
    public static function sort(array $names): array
    {
        $names = array_values(array_unique(array_filter(array_map('trim', $names), static fn ($n) => $n !== '')));
        $top = array_values(array_filter(self::TOP, static fn ($t) => in_array($t, $names, true)));
        $others = array_values(array_filter($names, static fn ($n) => !in_array($n, self::TOP, true)));
        usort($others, static fn ($a, $b) => strcoll($a, $b));
        return array_merge($top, $others);
    }
}
