<?php

namespace Database\Seeders;

use App\Enums\ChampType;
use App\Models\Option;
use Illuminate\Database\Seeder;

class OptionSeeder extends Seeder
{
    public function run(): void
    {
        $optionsChamp2 = [
            'Category00',
            'Category88',
            'Category99',
        ];

        $optionsChamp3 = [
            'FormatA',
            'FormatB',
            'FormatC',
        ];

        foreach ($optionsChamp2 as $name) {
            Option::updateOrCreate(
                ['name' => $name, 'champ' => ChampType::CHAMP2],
                []
            );
        }

        foreach ($optionsChamp3 as $name) {
            Option::updateOrCreate(
                ['name' => $name, 'champ' => ChampType::CHAMP3],
                []
            );
        }
    }
}
