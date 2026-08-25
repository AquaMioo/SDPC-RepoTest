<?php

namespace App\Enums;

/**
 * What line of work a client is in.
 *
 * A fixed list rather than free text, because a student browsing the client
 * directory wants to compare like with like, and "retail", "Retail Store" and
 * "retailer" typed by three businesses are three categories to a database and
 * one to a person.
 *
 * Chosen for San Jose Del Monte rather than copied from a global taxonomy: the
 * businesses on this platform are hardware suppliers, sari-sari and grocery
 * retailers, clinics, schools and small logistics outfits, so those come
 * first and the abstractions are left out. Other covers the rest without
 * pretending the list is complete.
 */
enum Industry: string
{
    case RetailAndTrading = 'retail_and_trading';
    case FoodAndBeverage = 'food_and_beverage';
    case ConstructionAndHardware = 'construction_and_hardware';
    case HealthAndWellness = 'health_and_wellness';
    case EducationAndTraining = 'education_and_training';
    case LogisticsAndTransport = 'logistics_and_transport';
    case BusinessConsulting = 'business_consulting';
    case TechnologyAndSoftware = 'technology_and_software';
    case RealEstateAndRentals = 'real_estate_and_rentals';
    case AgricultureAndFarming = 'agriculture_and_farming';
    case ManufacturingAndProduction = 'manufacturing_and_production';
    case CreativeAndMedia = 'creative_and_media';
    case NonProfitAndCommunity = 'non_profit_and_community';
    case Other = 'other';

    /**
     * Get the display label for the industry.
     */
    public function label(): string
    {
        return match ($this) {
            self::RetailAndTrading => __('Retail and trading'),
            self::FoodAndBeverage => __('Food and beverage'),
            self::ConstructionAndHardware => __('Construction and hardware'),
            self::HealthAndWellness => __('Health and wellness'),
            self::EducationAndTraining => __('Education and training'),
            self::LogisticsAndTransport => __('Logistics and transport'),
            self::BusinessConsulting => __('Business consulting'),
            self::TechnologyAndSoftware => __('Technology and software'),
            self::RealEstateAndRentals => __('Real estate and rentals'),
            self::AgricultureAndFarming => __('Agriculture and farming'),
            self::ManufacturingAndProduction => __('Manufacturing and production'),
            self::CreativeAndMedia => __('Creative and media'),
            self::NonProfitAndCommunity => __('Non-profit and community'),
            self::Other => __('Other'),
        };
    }

    /**
     * Get every industry as a value/label pair, sorted the way it is read.
     *
     * Alphabetical by label rather than in declaration order: a select of
     * fourteen entries is scanned, not read top to bottom. "Other" is pushed
     * to the end, where a fallback belongs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = array_map(
            fn (self $industry): array => [
                'value' => $industry->value,
                'label' => $industry->label(),
            ],
            array_filter(self::cases(), fn (self $industry): bool => $industry !== self::Other),
        );

        usort($options, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        return [
            ...$options,
            ['value' => self::Other->value, 'label' => self::Other->label()],
        ];
    }
}
