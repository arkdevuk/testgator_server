<?php

namespace App\Classes;

final class TextUtils
{
    public static function stringToWordsArray(string $str): array
    {
        $str = self::normalize($str);
        // use regex to split string into words
        $words = preg_split('/\s+/', $str);
        // remove useless words like "le" "les" "l'"
        $words = self::removeFrenchStopWords($words);
        // foreach $word in array remove symbols
        $newWords = [];
        foreach ($words as $key => $word) {
            $words[$key] = preg_replace('/[^a-z0-9]/', '', $word);
            $v = trim($words[$key]);
            if (!empty($v)) {
                $newWords[] = $v;
            }
        }
        return $newWords;
    }

    public static function normalize(string $str): string
    {
        $str = strtolower($str);
        $str = self::removeAccents($str);
        $str = self::normalizeSpacing($str);
        return $str;
    }

    public static function removeAccents(string $str): string
    {
        // remove all possible accented chars and replace with non accented version
        return str_replace(
            ['à', 'â', 'ä', 'á', 'ã', 'å', 'À', 'Â', 'Ä', 'Á', 'Ã', 'Å', 'æ', 'Æ', 'ç', 'Ç', 'é', 'è', 'ê', 'ë', 'É', 'È', 'Ê', 'Ë', 'í', 'ì', 'î', 'ï', 'Í', 'Ì', 'Î', 'Ï', 'ñ', 'Ñ', 'ó', 'ò', 'ô', 'ö', 'õ', 'Ó', 'Ò', 'Ô', 'Ö', 'Õ', 'œ', 'Œ', 'ß', 'ú', 'ù', 'û', 'ü', 'Ú', 'Ù', 'Û', 'Ü', 'ÿ', 'ý', 'Ý'],
            ['a', 'a', 'a', 'a', 'a', 'a', 'A', 'A', 'A', 'A', 'A', 'A', 'ae', 'AE', 'c', 'C', 'e', 'e', 'e', 'e', 'E', 'E', 'E', 'E', 'i', 'i', 'i', 'i', 'I', 'I', 'I', 'I', 'n', 'N', 'o', 'o', 'o', 'o', 'o', 'O', 'O', 'O', 'O', 'O', 'oe', 'OE', 'ss', 'u', 'u', 'u', 'u', 'U', 'U', 'U', 'U', 'y', 'y', 'Y'],
            $str
        );
    }

    public static function normalizeSpacing(string $str): string
    {
        return preg_replace('/\s+/', ' ', $str);
    }

    public static function removeFrenchStopWords(array $words): array
    {
        // remove useless words like "le" "les" "l'"
        $stopWords = [
            'le', 'la', 'les', 'l\'', 'un', 'une', 'des', 'du', 'de', 'd\'', 'à', 'au', 'aux', 'et', 'ou', 'où', 'mais', 'donc', 'or', 'ni', 'car', 'ce', 'cet', 'cette', 'ces', 'ceux', 'celui', 'celle', 'celles', 'ceux', 'ceci', 'cela', 'ça', 'ici', 'là', 'lorsque', 'puisque', 'parce que', 'si', 'comme', 'quand', 'que', 'quoi', 'qui', 'qu\'', 'quand', 'quel', 'quelle', 'quelles', 'quels', 'qu\'est-ce', 'qu\'il', 'qu\'elle', 'qu\'on', 'qu\'en', 'qu\'y', 'qu\'a', 'qu\'au', 'qu\'aux', 'qu\'un', 'qu\'une', 'qu\'il', 'qu\'elle', 'qu\'elles', 'qu\'ils', 'qu\'elles', 'qu\'on', 'qu\'en', 'qu\'y', 'qu\'a', 'qu\'au', 'qu\'aux', 'qu\'un', 'qu\'une', 'qu\'on', 'qu\'en', 'qu\'y', 'qu\'a', 'qu\'au', 'qu\'aux', 'qu\'un', 'qu\'une', 'qu\'on', 'qu\'en', 'qu\'y', 'qu\'a', 'qu\'au', 'qu\'aux', 'qu\'un', 'qu\'une', 'qu\'on', 'qu\'en', 'qu\'y', 'qu\'a', 'qu\'au', 'qu\'aux', 'qu\'un', 'qu\'une', 'qu\'on', 'qu\'en', 'qu\'y', 'qu\'a', 'qu\'au', 'qu\'aux', 'qu\'un', 'qu\'une', 'qu\'on', 'qu\'en', 'qu\'y', 'qu\'a', 'qu\'au', 'qu\'aux', 'qu\'un', 'qu\'une',
        ];
        return array_diff($words, $stopWords);
    }

    public static function removeNonAlphaNumeric(string $property_name): string
    {
        return preg_replace('/[^a-zA-Z0-9\-\_]/', '', $property_name);
    }
}
