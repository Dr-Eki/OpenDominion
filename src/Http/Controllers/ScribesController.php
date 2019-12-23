<?php

namespace OpenDominion\Http\Controllers;

use OpenDominion\Calculators\Dominion\Actions\TrainingCalculator;
use OpenDominion\Helpers\BuildingHelper;
use OpenDominion\Helpers\EspionageHelper;
use OpenDominion\Helpers\LandHelper;
use OpenDominion\Helpers\RaceHelper;
use OpenDominion\Helpers\SpellHelper;
use OpenDominion\Helpers\UnitHelper;
use OpenDominion\Models\Race;

class ScribesController extends AbstractController
{
    public function getRaces()
    {

        $races = collect(Race::orderBy('name')->get())->groupBy('alignment')->toArray();
        return view('pages.scribes.races', [
            'goodRaces' => $races['good'],
            'evilRaces' => $races['evil'],
            'npcRaces' => $races['npc'],
            'independentRaces' => $races['independent'],
        ]);
    }

    public function getRace(string $raceName)
    {
        $raceName = ucwords(str_replace('-', ' ', $raceName));

        $race = Race::where('name', $raceName)
            ->firstOrFail();

        return view('pages.scribes.race', [
            'landHelper' => app(LandHelper::class),
            'unitHelper' => app(UnitHelper::class),
            'raceHelper' => app(RaceHelper::class),
            'spellHelper' => app(SpellHelper::class),
            'trainingCalculator' => app(TrainingCalculator::class),
            'race' => $race,
        ]);
    }

    public function getConstruction()
    {
        $buildingHelper = app(BuildingHelper::class);

        $buildingTypesPerLandType = $buildingHelper->getBuildingTypesByRace();
        $buildingTypeWithLandType = [];
        foreach ($buildingTypesPerLandType as $landType => $buildingTypes) {
            foreach($buildingTypes as $buildingType) {
                $buildingTypeWithLandType[$buildingType] = $landType;
            }
        }

        $buildingTypeWithLandType['home'] = null;

        ksort($buildingTypeWithLandType);

        $races = collect(Race::where('playable', 1)->orderBy('name')->get())->groupBy('alignment')->toArray();
        return view('pages.scribes.construction', [
            'goodRaces' => $races['good'],
            'evilRaces' => $races['evil'],
            #'npcRaces' => $races['npc'],
            #'independentRaces' => $races['independent'],
            'buildingTypeWithLandType' => $buildingTypeWithLandType,
            'buildingHelper' => $buildingHelper,
            'landHelper' => app(LandHelper::class),
        ]);
    }

    public function getEspionage()
    {
        return view('pages.scribes.espionage', [
            'espionageHelper' => app(EspionageHelper::class)
        ]);
    }

    public function getMagic()
    {
        return view('pages.scribes.magic', [
            'spellHelper' => app(SpellHelper::class)
        ]);
    }
}
