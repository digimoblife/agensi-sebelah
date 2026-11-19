<?php

namespace App\Enums;

enum KnowledgeKind: string
{
    case Fact = 'fact';
    case Faq = 'faq';
    case Policy = 'policy';
    case Price = 'price';
    case Schedule = 'schedule';
    case Testimonial = 'testimonial';
    case DoDont = 'do_dont';

    public function label(): string
    {
        return match ($this) {
            self::Fact => 'Fakta',
            self::Faq => 'FAQ',
            self::Policy => 'Kebijakan',
            self::Price => 'Harga',
            self::Schedule => 'Jadwal',
            self::Testimonial => 'Testimoni',
            self::DoDont => 'Lakukan / hindari',
        };
    }
}
